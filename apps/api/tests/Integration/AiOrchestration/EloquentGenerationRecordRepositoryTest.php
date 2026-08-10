<?php

declare(strict_types=1);

namespace Tests\Integration\AiOrchestration;

use App\AiOrchestration\Domain\CapabilityRequirement;
use App\AiOrchestration\Domain\GenerationRecord;
use App\AiOrchestration\Domain\GenerationStatus;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;
use App\AiOrchestration\Infrastructure\EloquentGenerationRecordRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesAiOrchestrationFixtures;
use Tests\TestCase;

class EloquentGenerationRecordRepositoryTest extends TestCase
{
    use CreatesAiOrchestrationFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_a_record_including_its_capability_requirement(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentGenerationRecordRepository;
        $record = GenerationRecord::request(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            workflowName: 'brd-synthesis',
            capabilityRequirement: new CapabilityRequirement(
                structuredOutput: StructuredOutputCapability::StrictSchema,
                toolUse: ToolUseCapability::Sequential,
                streaming: StreamingCapability::Text,
                minContextWindow: 50000,
                requiresVision: true,
            ),
            requestedBy: $userId,
        );
        $repository->save($record);

        $found = $repository->findById($record->id);

        $this->assertNotNull($found);
        $this->assertSame('brd-synthesis', $found->workflowName);
        $this->assertSame(GenerationStatus::Queued, $found->status());
        $this->assertSame(StructuredOutputCapability::StrictSchema, $found->capabilityRequirement->structuredOutput);
        $this->assertSame(50000, $found->capabilityRequirement->minContextWindow);
        $this->assertTrue($found->capabilityRequirement->requiresVision);
    }

    public function test_a_second_save_persists_the_selected_model(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentGenerationRecordRepository;
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
        $repository->save($record);

        $record->selectModel('claude-sonnet-5-20260101');
        $repository->save($record);

        $found = $repository->findById($record->id);

        $this->assertNotNull($found);
        $this->assertSame(GenerationStatus::Selected, $found->status());
        $this->assertSame('claude-sonnet-5-20260101', $found->selectedModelId());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentGenerationRecordRepository)->findById((string) Str::uuid()));
    }

    public function test_find_all_returns_all_saved_records(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $userId = $this->createUser();
        $repository = new EloquentGenerationRecordRepository;

        $first = GenerationRecord::request(
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
        $repository->save($first);

        $second = GenerationRecord::request(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            workflowName: 'srs-synthesis',
            capabilityRequirement: new CapabilityRequirement(
                structuredOutput: StructuredOutputCapability::JsonMode,
                toolUse: ToolUseCapability::Sequential,
                streaming: StreamingCapability::Text,
                minContextWindow: 4000,
                requiresVision: true,
                requiresDeterministicSeed: true,
            ),
            requestedBy: $userId,
        );
        $second->fail('No active model satisfies the requested capabilities and tenant provider policy.');
        $repository->save($second);

        $all = $repository->findAll();
        $byId = [];
        foreach ($all as $record) {
            $byId[$record->id] = $record;
        }

        $this->assertCount(2, $all);
        $this->assertSame(GenerationStatus::Failed, $byId[$second->id]->status());
        $this->assertSame('No active model satisfies the requested capabilities and tenant provider policy.', $byId[$second->id]->failureReason());
        $this->assertSame(GenerationStatus::Queued, $byId[$first->id]->status());
        $this->assertFalse($byId[$first->id]->capabilityRequirement->requiresVision);
    }

    public function test_find_all_returns_empty_array_when_no_records_exist(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertSame([], (new EloquentGenerationRecordRepository)->findAll());
    }
}
