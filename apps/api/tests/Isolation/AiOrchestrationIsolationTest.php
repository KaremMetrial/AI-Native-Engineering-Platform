<?php

declare(strict_types=1);

namespace Tests\Isolation;

use App\AiOrchestration\Domain\CapabilityRequirement;
use App\AiOrchestration\Domain\GenerationRecord;
use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\TenantProviderPolicy;
use App\AiOrchestration\Domain\ToolUseCapability;
use App\AiOrchestration\Infrastructure\EloquentGenerationRecordRepository;
use App\AiOrchestration\Infrastructure\EloquentTenantProviderPolicyRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesAiOrchestrationFixtures;
use Tests\TestCase;

/**
 * The real cross-tenant isolation proof for the AI Orchestration module's
 * tenant-scoped tables (tenant_provider_policies, generation_records).
 * model_registry_entries is deliberately NOT covered here -- it has no
 * tenant_id (see its migration's docblock); every tenant reading the same
 * global configuration is the intended behavior, not a leak, and that is
 * asserted directly below rather than left as an unstated assumption.
 */
class AiOrchestrationIsolationTest extends TestCase
{
    use CreatesAiOrchestrationFixtures;
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

    public function test_a_tenant_only_sees_its_own_provider_policy(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $policies = new EloquentTenantProviderPolicyRepository;

        $this->tenantContext->bind($tenantA);
        $policies->save(TenantProviderPolicy::restrict($tenantA, [Provider::Anthropic]));

        $this->tenantContext->bind($tenantB);
        $policies->save(TenantProviderPolicy::restrict($tenantB, [Provider::OpenAi]));

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('tenant_provider_policies')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_a_tenant_only_sees_its_own_generation_records(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildGenerationRecordWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $this->buildGenerationRecordWithinNewTenant($tenantB);

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('generation_records')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenantA, $rows->first()->tenant_id);
    }

    public function test_direct_id_access_to_another_tenants_generation_record_returns_nothing(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $record = $this->buildGenerationRecordWithinNewTenant($tenantA);

        $this->tenantContext->bind($tenantB);
        $row = DB::table('generation_records')->where('id', $record->id)->first();

        $this->assertNull($row);
    }

    public function test_a_tenant_cannot_write_a_generation_record_into_another_tenant(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $userId = $this->createUser();

        $this->expectException(QueryException::class);

        DB::transaction(function () use ($tenantB, $userId): void {
            DB::table('generation_records')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantB,
                'workflow_name' => 'rogue-workflow',
                'capability_requirement' => json_encode([
                    'structured_output' => 'none',
                    'tool_use' => 'none',
                    'streaming' => 'none',
                    'min_context_window' => 1,
                    'requires_vision' => false,
                    'requires_deterministic_seed' => false,
                ]),
                'requested_by' => $userId,
                'status' => 'queued',
                'created_at' => now(),
            ]);
        });
    }

    public function test_missing_tenant_context_returns_zero_rows_not_another_tenants_data(): void
    {
        $tenantA = $this->createTenant();

        $this->tenantContext->bind($tenantA);
        $this->buildGenerationRecordWithinNewTenant($tenantA);
        $this->tenantContext->clear();

        $this->assertCount(0, DB::table('generation_records')->get());
    }

    public function test_row_security_off_bypass_attempt_is_blocked(): void
    {
        $tenantA = $this->createTenant();
        $this->tenantContext->bind($tenantA);
        $this->buildGenerationRecordWithinNewTenant($tenantA);

        DB::statement('SET row_security = off');

        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::table('generation_records')->get();
        });
    }

    public function test_runtime_role_cannot_disable_row_level_security(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE generation_records DISABLE ROW LEVEL SECURITY');
        });
    }

    public function test_runtime_role_does_not_own_the_tenant_scoped_tables(): void
    {
        foreach (['tenant_provider_policies', 'generation_records'] as $table) {
            $owner = DB::selectOne('SELECT tableowner FROM pg_tables WHERE tablename = ?', [$table]);

            $this->assertNotSame('platform_app', $owner->tableowner, "platform_app must not own {$table} (D-55)");
        }
    }

    public function test_the_model_registry_is_visible_regardless_of_tenant_context_by_design(): void
    {
        $this->registerModel(Provider::Anthropic, active: true);

        $tenantA = $this->createTenant();
        $this->tenantContext->bind($tenantA);
        $seenFromA = DB::table('model_registry_entries')->count();

        $tenantB = $this->createTenant();
        $this->tenantContext->bind($tenantB);
        $seenFromB = DB::table('model_registry_entries')->count();

        $this->tenantContext->clear();
        $seenWithNoTenant = DB::table('model_registry_entries')->count();

        $this->assertSame($seenFromA, $seenFromB);
        $this->assertSame($seenFromA, $seenWithNoTenant);
        $this->assertGreaterThan(0, $seenWithNoTenant);
    }

    private function buildGenerationRecordWithinNewTenant(string $tenantId): GenerationRecord
    {
        $userId = $this->createUser();

        $record = GenerationRecord::request(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            workflowName: 'brd-synthesis',
            capabilityRequirement: new CapabilityRequirement(
                structuredOutput: StructuredOutputCapability::None,
                toolUse: ToolUseCapability::None,
                streaming: StreamingCapability::None,
                minContextWindow: 1,
            ),
            requestedBy: $userId,
        );
        (new EloquentGenerationRecordRepository)->save($record);

        return $record;
    }
}
