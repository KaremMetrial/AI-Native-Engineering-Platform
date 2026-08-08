<?php

declare(strict_types=1);

namespace Tests\Isolation;

use App\Discovery\Domain\Assumption;
use App\Discovery\Domain\Constraint;
use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\Question;
use App\Discovery\Domain\Response;
use App\Discovery\Infrastructure\EloquentAssumptionRepository;
use App\Discovery\Infrastructure\EloquentConstraintRepository;
use App\Discovery\Infrastructure\EloquentDiscoverySessionRepository;
use App\Discovery\Infrastructure\EloquentQuestionRepository;
use App\Discovery\Infrastructure\EloquentResponseRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesDiscoveryFixtures;
use Tests\TestCase;

/**
 * The real cross-tenant isolation proof for the Discovery module
 * (discovery_sessions, discovery_questions, discovery_responses,
 * discovery_assumptions, discovery_constraints). Mirrors
 * GraphIsolationTest's and MembershipIsolationTest's structure of proofs.
 */
class DiscoveryIsolationTest extends TestCase
{
    use CreatesDiscoveryFixtures;
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

    public function test_a_tenant_only_sees_its_own_discovery_sessions(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildSessionWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $this->buildSessionWithinNewTenant($tenantB);

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('discovery_sessions')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_a_tenant_only_sees_its_own_questions_and_responses(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildResponseWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $this->buildResponseWithinNewTenant($tenantB);

        $this->tenantContext->bind($tenantA);
        $this->assertCount(1, DB::table('discovery_questions')->get());
        $this->assertCount(1, DB::table('discovery_responses')->get());
    }

    public function test_a_tenant_only_sees_its_own_assumptions(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildAssumptionWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $this->buildAssumptionWithinNewTenant($tenantB);

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('discovery_assumptions')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_a_tenant_only_sees_its_own_constraints(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildConstraintWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $this->buildConstraintWithinNewTenant($tenantB);

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('discovery_constraints')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_direct_id_access_to_another_tenants_discovery_session_returns_nothing(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $session = $this->buildSessionWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $row = DB::table('discovery_sessions')->where('id', $session->id)->first();

        $this->assertNull($row);
    }

    public function test_a_tenant_cannot_write_a_discovery_session_into_another_tenant(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $projectId = $this->createProject($tenantA);
        $userId = $this->createUser();

        $this->expectException(QueryException::class);

        DB::transaction(function () use ($tenantB, $projectId, $userId): void {
            DB::table('discovery_sessions')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantB,
                'project_id' => $projectId,
                'title' => 'Rogue session',
                'status' => 'in_progress',
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    public function test_missing_tenant_context_returns_zero_rows_not_another_tenants_data(): void
    {
        $tenantA = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildSessionWithinNewTenant($tenantA);
        $this->tenantContext->clear();

        $this->assertCount(0, DB::table('discovery_sessions')->get());
    }

    public function test_row_security_off_bypass_attempt_is_blocked(): void
    {
        $tenantA = $this->createTenant();
        $this->tenantContext->bind($tenantA);
        $this->buildSessionWithinNewTenant($tenantA);

        DB::statement('SET row_security = off');

        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::table('discovery_sessions')->get();
        });
    }

    public function test_runtime_role_cannot_disable_row_level_security(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE discovery_sessions DISABLE ROW LEVEL SECURITY');
        });
    }

    public function test_runtime_role_does_not_own_the_discovery_tables(): void
    {
        $tables = ['discovery_sessions', 'discovery_questions', 'discovery_responses', 'discovery_assumptions', 'discovery_constraints'];

        foreach ($tables as $table) {
            $owner = DB::selectOne('SELECT tableowner FROM pg_tables WHERE tablename = ?', [$table]);

            $this->assertNotSame('platform_app', $owner->tableowner, "platform_app must not own {$table} (D-55)");
        }
    }

    private function buildSessionWithinNewTenant(string $tenantId): DiscoverySession
    {
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $session = DiscoverySession::start((string) Str::uuid(), $tenantId, $projectId, 'Acme kickoff', $userId);
        (new EloquentDiscoverySessionRepository)->save($session);

        return $session;
    }

    private function buildResponseWithinNewTenant(string $tenantId): Response
    {
        $session = $this->buildSessionWithinNewTenant($tenantId);

        $question = Question::ask((string) Str::uuid(), $tenantId, $session->id, 'What problem are you solving?', 1, $session->createdBy);
        (new EloquentQuestionRepository)->save($question);

        $response = Response::record((string) Str::uuid(), $tenantId, $question->id, 'We lose bids on turnaround time.', $session->createdBy);
        (new EloquentResponseRepository)->save($response);

        return $response;
    }

    private function buildAssumptionWithinNewTenant(string $tenantId): Assumption
    {
        $session = $this->buildSessionWithinNewTenant($tenantId);

        $assumption = Assumption::capture((string) Str::uuid(), $tenantId, $session->id, 'Stakeholders will respond within 48 hours.', $session->createdBy);
        (new EloquentAssumptionRepository)->save($assumption);

        return $assumption;
    }

    private function buildConstraintWithinNewTenant(string $tenantId): Constraint
    {
        $session = $this->buildSessionWithinNewTenant($tenantId);

        $constraint = Constraint::capture((string) Str::uuid(), $tenantId, $session->id, 'Must integrate with the existing Jira instance.', $session->createdBy);
        (new EloquentConstraintRepository)->save($constraint);

        return $constraint;
    }
}
