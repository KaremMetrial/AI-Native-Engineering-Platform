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
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

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

    public function findAll(): array
    {
        // Plain query builder, not Eloquent -- without Larastan (D-206)
        // PHPStan's inference of chained Eloquent Builder generics is
        // unreliable; DB::table() has a single, well-typed stdClass-row
        // contract instead (see Graph's EloquentProjectRepository for the
        // same workaround). Most recent request first: this backs a
        // request-history list, not a paginated feed.
        $rows = DB::table('generation_records')->orderByDesc('created_at')->get();

        return array_values($rows->map(fn (mixed $row): GenerationRecord => $this->rowToDomain($row))->all());
    }

    private function rowToDomain(mixed $row): GenerationRecord
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from generation_records.');
        }

        $selectedModelId = $row->selected_model_id;
        $failureReason = $row->failure_reason;

        return new GenerationRecord(
            id: $this->requireString($row->id),
            tenantId: $this->requireString($row->tenant_id),
            workflowName: $this->requireString($row->workflow_name),
            capabilityRequirement: $this->requirementFromArray($this->decodeCapabilityRequirement($row->capability_requirement)),
            requestedBy: $this->requireString($row->requested_by),
            status: GenerationStatus::from($this->requireString($row->status)),
            createdAt: new DateTimeImmutable($this->requireString($row->created_at)),
            selectedModelId: is_string($selectedModelId) ? $selectedModelId : null,
            failureReason: is_string($failureReason) ? $failureReason : null,
        );
    }

    /**
     * The query builder skips Eloquent's array cast, so the JSON column
     * comes back as a raw string here (same wrinkle as Requirements'
     * acceptance_criteria -- see its README).
     *
     * @return array<string, mixed>
     */
    private function decodeCapabilityRequirement(mixed $value): array
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected a JSON string capability_requirement column value.');
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Expected capability_requirement to decode to an array.');
        }

        $result = [];
        foreach ($decoded as $key => $item) {
            if (! is_string($key)) {
                throw new RuntimeException('Expected capability_requirement keys to be strings.');
            }
            $result[$key] = $item;
        }

        return $result;
    }

    private function requireString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected a string column value.');
        }

        return $value;
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
