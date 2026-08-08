<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use App\AiOrchestration\Domain\CapabilityRequirement;
use App\AiOrchestration\Domain\GenerationRecord;
use App\AiOrchestration\Domain\GenerationRecordRepository;
use App\AiOrchestration\Domain\GenerationStatus;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;
use RuntimeException;

class EloquentGenerationRecordRepository implements GenerationRecordRepository
{
    public function save(GenerationRecord $record): void
    {
        EloquentGenerationRecord::query()->updateOrCreate(
            ['id' => $record->id],
            [
                'tenant_id' => $record->tenantId,
                'workflow_name' => $record->workflowName,
                'capability_requirement' => [
                    'structured_output' => $record->capabilityRequirement->structuredOutput->value,
                    'tool_use' => $record->capabilityRequirement->toolUse->value,
                    'streaming' => $record->capabilityRequirement->streaming->value,
                    'min_context_window' => $record->capabilityRequirement->minContextWindow,
                    'requires_vision' => $record->capabilityRequirement->requiresVision,
                    'requires_deterministic_seed' => $record->capabilityRequirement->requiresDeterministicSeed,
                ],
                'requested_by' => $record->requestedBy,
                'status' => $record->status()->value,
                'selected_model_id' => $record->selectedModelId(),
                'failure_reason' => $record->failureReason(),
                'created_at' => $record->createdAt,
            ],
        );
    }

    public function findById(string $id): ?GenerationRecord
    {
        $model = EloquentGenerationRecord::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(EloquentGenerationRecord $model): GenerationRecord
    {
        return new GenerationRecord(
            id: $model->id,
            tenantId: $model->tenant_id,
            workflowName: $model->workflow_name,
            capabilityRequirement: $this->requirementFromArray($model->capability_requirement),
            requestedBy: $model->requested_by,
            status: GenerationStatus::from($model->status),
            createdAt: $model->created_at->toDateTimeImmutable(),
            selectedModelId: $model->selected_model_id,
            failureReason: $model->failure_reason,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function requirementFromArray(array $row): CapabilityRequirement
    {
        $structuredOutput = $row['structured_output'] ?? null;
        $toolUse = $row['tool_use'] ?? null;
        $streaming = $row['streaming'] ?? null;
        $minContextWindow = $row['min_context_window'] ?? null;

        if (! is_string($structuredOutput) || ! is_string($toolUse) || ! is_string($streaming) || ! is_int($minContextWindow)) {
            throw new RuntimeException('Malformed capability_requirement row.');
        }

        return new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::from($structuredOutput),
            toolUse: ToolUseCapability::from($toolUse),
            streaming: StreamingCapability::from($streaming),
            minContextWindow: $minContextWindow,
            requiresVision: (bool) ($row['requires_vision'] ?? false),
            requiresDeterministicSeed: (bool) ($row['requires_deterministic_seed'] ?? false),
        );
    }
}
