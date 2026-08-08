<?php

declare(strict_types=1);

namespace Tests\Isolation;

use App\Graph\Domain\Approval;
use App\Graph\Domain\ApprovalDecision;
use App\Graph\Domain\Artifact;
use App\Graph\Domain\ArtifactLink;
use App\Graph\Domain\LinkType;
use App\Graph\Infrastructure\EloquentApprovalRepository;
use App\Graph\Infrastructure\EloquentArtifactLinkRepository;
use App\Graph\Infrastructure\EloquentArtifactRepository;
use App\Graph\Infrastructure\PostgresImpactTraversal;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesGraphFixtures;
use Tests\TestCase;

/**
 * The real cross-tenant isolation proof for the Delivery Graph kernel
 * (projects, artifacts, artifact_versions, artifact_links, approvals),
 * replacing the Phase 0 graph_poc_* spike tables (deleted alongside them --
 * see database/migrations/2026_08_08_000003_graph_create_artifact_versions_table.php).
 * Mirrors MembershipIsolationTest's structure of proofs.
 */
class GraphIsolationTest extends TestCase
{
    use CreatesGraphFixtures;
    use DatabaseTransactions;

    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantContext = $this->app->make(TenantContext::class);
    }

    protected function tearDown(): void
    {
        $this->tenantContext->clear();
        DB::statement('SET row_security = on');
        parent::tearDown();
    }

    public function test_a_tenant_only_sees_its_own_projects(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->createProject($tenantA);

        $this->tenantContext->bind($tenantB);
        $this->createProject($tenantB);

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('projects')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_a_tenant_only_sees_its_own_artifacts_and_versions(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $artifacts = new EloquentArtifactRepository;

        $this->tenantContext->bind($tenantA);
        $artifacts->save($this->buildArtifact($tenantA, $this->createProject($tenantA), $this->createUser()));

        $this->tenantContext->bind($tenantB);
        $artifacts->save($this->buildArtifact($tenantB, $this->createProject($tenantB), $this->createUser()));

        $this->tenantContext->bind($tenantA);
        $this->assertCount(1, DB::table('artifacts')->get());
        $this->assertCount(1, DB::table('artifact_versions')->get());
    }

    public function test_a_tenant_only_sees_its_own_artifact_links(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $links = new EloquentArtifactLinkRepository;

        $this->tenantContext->bind($tenantA);
        $links->save($this->buildLinkWithinNewTenant($tenantA));

        $this->tenantContext->bind($tenantB);
        $links->save($this->buildLinkWithinNewTenant($tenantB));

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('artifact_links')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_a_tenant_only_sees_its_own_approvals(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $approvals = new EloquentApprovalRepository;

        $this->tenantContext->bind($tenantA);
        $approvals->save($this->buildApprovalWithinNewTenant($tenantA));

        $this->tenantContext->bind($tenantB);
        $approvals->save($this->buildApprovalWithinNewTenant($tenantB));

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('approvals')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_direct_id_access_to_another_tenants_artifact_link_returns_nothing(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $links = new EloquentArtifactLinkRepository;

        $this->tenantContext->bind($tenantA);
        $link = $this->buildLinkWithinNewTenant($tenantA);
        $links->save($link);

        $this->tenantContext->bind($tenantB);
        $row = DB::table('artifact_links')->where('id', $link->id)->first();

        $this->assertNull($row);
    }

    public function test_a_tenant_cannot_write_an_artifact_link_into_another_tenant(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $userId = $this->createUser();

        $this->tenantContext->bind($tenantA);
        $artifacts = new EloquentArtifactRepository;
        $fromArtifact = $this->buildArtifact($tenantA, $this->createProject($tenantA), $userId);
        $toArtifact = $this->buildArtifact($tenantA, $this->createProject($tenantA), $userId);
        $artifacts->save($fromArtifact);
        $artifacts->save($toArtifact);

        $this->expectException(QueryException::class);

        DB::transaction(function () use ($tenantB, $fromArtifact, $toArtifact, $userId): void {
            DB::table('artifact_links')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantB,
                'from_version_id' => $fromArtifact->currentVersionId(),
                'to_version_id' => $toArtifact->currentVersionId(),
                'link_type' => LinkType::References->value,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    public function test_missing_tenant_context_returns_zero_rows_not_another_tenants_data(): void
    {
        $tenantA = $this->createTenant();
        $links = new EloquentArtifactLinkRepository;

        $this->tenantContext->bind($tenantA);
        $links->save($this->buildLinkWithinNewTenant($tenantA));
        $this->tenantContext->clear();

        $this->assertCount(0, DB::table('artifact_links')->get());
    }

    public function test_row_security_off_bypass_attempt_is_blocked(): void
    {
        $tenantA = $this->createTenant();
        $this->tenantContext->bind($tenantA);
        (new EloquentArtifactLinkRepository)->save($this->buildLinkWithinNewTenant($tenantA));

        DB::statement('SET row_security = off');

        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::table('artifact_links')->get();
        });
    }

    public function test_runtime_role_cannot_disable_row_level_security(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE artifact_links DISABLE ROW LEVEL SECURITY');
        });
    }

    public function test_runtime_role_does_not_own_the_graph_tables(): void
    {
        foreach (['projects', 'artifacts', 'artifact_versions', 'artifact_links', 'approvals'] as $table) {
            $owner = DB::selectOne('SELECT tableowner FROM pg_tables WHERE tablename = ?', [$table]);

            $this->assertNotSame('platform_app', $owner->tableowner, "platform_app must not own {$table} (D-55)");
        }
    }

    public function test_impact_traversal_is_scoped_by_row_level_security(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $links = new EloquentArtifactLinkRepository;

        $this->tenantContext->bind($tenantB);
        $link = $this->buildLinkWithinNewTenant($tenantB);
        $links->save($link);

        // Tenant B's link and versions genuinely exist -- the seed id is
        // valid, just invisible under tenant A's session. If RLS did not
        // apply inside the recursive CTE (only on the outer SELECT, say),
        // this would return tenant B's task instead of an empty result.
        $this->tenantContext->bind($tenantA);
        $impacted = (new PostgresImpactTraversal)->traverse($link->toVersionId, 5);

        $this->assertSame([], $impacted);
    }

    private function buildArtifact(string $tenantId, string $projectId, string $userId): Artifact
    {
        return Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'brd', (string) Str::uuid(), 'Content', $userId);
    }

    private function buildLinkWithinNewTenant(string $tenantId): ArtifactLink
    {
        $artifacts = new EloquentArtifactRepository;
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $from = $this->buildArtifact($tenantId, $projectId, $userId);
        $to = $this->buildArtifact($tenantId, $projectId, $userId);
        $artifacts->save($from);
        $artifacts->save($to);

        return ArtifactLink::create(
            (string) Str::uuid(),
            $tenantId,
            $from->currentVersionId(),
            $to->currentVersionId(),
            LinkType::References,
            $userId,
        );
    }

    private function buildApprovalWithinNewTenant(string $tenantId): Approval
    {
        $artifacts = new EloquentArtifactRepository;
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $artifact = $this->buildArtifact($tenantId, $projectId, $userId);
        $artifacts->save($artifact);

        return Approval::record(
            (string) Str::uuid(),
            $tenantId,
            $artifact->currentVersionId(),
            $userId,
            ApprovalDecision::Approved,
            null,
        );
    }
}
