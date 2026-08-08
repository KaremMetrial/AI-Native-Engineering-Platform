<?php

declare(strict_types=1);

namespace Tests\Unit\AiOrchestration;

use App\AiOrchestration\Domain\CapabilityRequirement;
use App\AiOrchestration\Domain\GenerationRecord;
use App\AiOrchestration\Domain\GenerationStatus;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;
use DomainException;
use Tests\TestCase;

class GenerationRecordTest extends TestCase
{
    public function test_request_produces_a_queued_record(): void
    {
        $record = $this->requestRecord();

        $this->assertSame(GenerationStatus::Queued, $record->status());
        $this->assertNull($record->selectedModelId());
        $this->assertNull($record->failureReason());
    }

    public function test_select_model_transitions_to_selected(): void
    {
        $record = $this->requestRecord();

        $record->selectModel('claude-sonnet-5-20260101');

        $this->assertSame(GenerationStatus::Selected, $record->status());
        $this->assertSame('claude-sonnet-5-20260101', $record->selectedModelId());
    }

    public function test_select_model_throws_when_not_queued(): void
    {
        $record = $this->requestRecord();
        $record->selectModel('claude-sonnet-5-20260101');

        $this->expectException(DomainException::class);

        $record->selectModel('gpt-5');
    }

    public function test_fail_transitions_to_failed_with_a_reason(): void
    {
        $record = $this->requestRecord();

        $record->fail('No active model satisfies the requested capabilities.');

        $this->assertSame(GenerationStatus::Failed, $record->status());
        $this->assertSame('No active model satisfies the requested capabilities.', $record->failureReason());
    }

    public function test_fail_throws_when_not_queued(): void
    {
        $record = $this->requestRecord();
        $record->fail('First failure.');

        $this->expectException(DomainException::class);

        $record->fail('Second failure.');
    }

    private function requestRecord(): GenerationRecord
    {
        return GenerationRecord::request(
            id: 'record-1',
            tenantId: 'tenant-1',
            workflowName: 'brd-synthesis',
            capabilityRequirement: new CapabilityRequirement(
                structuredOutput: StructuredOutputCapability::None,
                toolUse: ToolUseCapability::None,
                streaming: StreamingCapability::None,
                minContextWindow: 1,
            ),
            requestedBy: 'user-1',
        );
    }
}
