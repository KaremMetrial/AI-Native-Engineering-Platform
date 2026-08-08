<?php

declare(strict_types=1);

namespace Tests\Isolation;

use App\Requirements\Domain\AcceptanceCriterion;
use App\Requirements\Domain\DocumentType;
use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Infrastructure\EloquentRequirementDocumentRepository;
use App\Requirements\Infrastructure\EloquentRequirementRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesRequirementsFixtures;
use Tests\TestCase;

/**
 * The real cross-tenant isolation proof for the Requirements module
 * (requirement_documents, requirements). Mirrors GraphIsolationTest's and
 * DiscoveryIsolationTest's structure of proofs.
 */
class RequirementsIsolationTest extends TestCase
{
    use CreatesRequirementsFixtures;
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

    public function test_a_tenant_only_sees_its_own_requirement_documents(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildDocumentWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $this->buildDocumentWithinNewTenant($tenantB);

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('requirement_documents')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_a_tenant_only_sees_its_own_requirements(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildRequirementWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $this->buildRequirementWithinNewTenant($tenantB);

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('requirements')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_direct_id_access_to_another_tenants_requirement_document_returns_nothing(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $document = $this->buildDocumentWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $row = DB::table('requirement_documents')->where('id', $document->id)->first();

        $this->assertNull($row);
    }

    public function test_a_tenant_cannot_write_a_requirement_document_into_another_tenant(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $projectId = $this->createProject($tenantA);
        $userId = $this->createUser();

        $this->expectException(QueryException::class);

        DB::transaction(function () use ($tenantB, $projectId, $userId): void {
            DB::table('requirement_documents')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantB,
                'project_id' => $projectId,
                'type' => 'brd',
                'title' => 'Rogue document',
                'status' => 'draft',
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    public function test_missing_tenant_context_returns_zero_rows_not_another_tenants_data(): void
    {
        $tenantA = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildDocumentWithinNewTenant($tenantA);
        $this->tenantContext->clear();

        $this->assertCount(0, DB::table('requirement_documents')->get());
    }

    public function test_row_security_off_bypass_attempt_is_blocked(): void
    {
        $tenantA = $this->createTenant();
        $this->tenantContext->bind($tenantA);
        $this->buildDocumentWithinNewTenant($tenantA);

        DB::statement('SET row_security = off');

        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::table('requirement_documents')->get();
        });
    }

    public function test_runtime_role_cannot_disable_row_level_security(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE requirement_documents DISABLE ROW LEVEL SECURITY');
        });
    }

    public function test_runtime_role_does_not_own_the_requirements_tables(): void
    {
        foreach (['requirement_documents', 'requirements'] as $table) {
            $owner = DB::selectOne('SELECT tableowner FROM pg_tables WHERE tablename = ?', [$table]);

            $this->assertNotSame('platform_app', $owner->tableowner, "platform_app must not own {$table} (D-55)");
        }
    }

    private function buildDocumentWithinNewTenant(string $tenantId): RequirementDocument
    {
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $document = RequirementDocument::create((string) Str::uuid(), $tenantId, $projectId, DocumentType::Brd, 'Acme BRD', $userId);
        (new EloquentRequirementDocumentRepository)->save($document);

        return $document;
    }

    private function buildRequirementWithinNewTenant(string $tenantId): Requirement
    {
        $document = $this->buildDocumentWithinNewTenant($tenantId);

        $requirement = Requirement::draft(
            (string) Str::uuid(),
            $tenantId,
            $document->id,
            'The system shall...',
            [new AcceptanceCriterion('Given..., the system does...')],
            $document->createdBy,
        );
        (new EloquentRequirementRepository)->save($requirement);

        return $requirement;
    }
}
