<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use App\AiOrchestration\Domain\ModelCapabilities;
use App\AiOrchestration\Domain\ModelPricing;
use App\AiOrchestration\Domain\ModelRegistryEntry;
use App\AiOrchestration\Domain\ModelRegistryRepository;
use App\AiOrchestration\Domain\ModelStatus;
use App\AiOrchestration\Domain\ModelTier;
use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;

class EloquentModelRegistryRepository implements ModelRegistryRepository
{
    public function save(ModelRegistryEntry $entry): void
    {
        EloquentModelRegistryEntry::query()->updateOrCreate(
            ['id' => $entry->id],
            [
                'model_id' => $entry->modelId,
                'provider' => $entry->provider->value,
                'tier' => $entry->tier->value,
                'structured_output' => $entry->capabilities->structuredOutput->value,
                'tool_use' => $entry->capabilities->toolUse->value,
                'streaming' => $entry->capabilities->streaming->value,
                'vision' => $entry->capabilities->vision,
                'deterministic_seed' => $entry->capabilities->deterministicSeed,
                'context_window' => $entry->contextWindow,
                'max_output' => $entry->maxOutput,
                'input_per_million_tokens_usd' => $entry->pricing->inputPerMillionTokensUsd,
                'output_per_million_tokens_usd' => $entry->pricing->outputPerMillionTokensUsd,
                'cached_input_per_million_tokens_usd' => $entry->pricing->cachedInputPerMillionTokensUsd,
                'status' => $entry->status()->value,
                'created_at' => $entry->createdAt,
            ],
        );
    }

    public function findById(string $id): ?ModelRegistryEntry
    {
        $model = EloquentModelRegistryEntry::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByStatus(ModelStatus $status): array
    {
        return array_values(
            EloquentModelRegistryEntry::query()
                ->where('status', $status->value)
                ->get()
                ->map(fn (EloquentModelRegistryEntry $m): ModelRegistryEntry => $this->toDomain($m))
                ->all(),
        );
    }

    private function toDomain(EloquentModelRegistryEntry $model): ModelRegistryEntry
    {
        return new ModelRegistryEntry(
            id: $model->id,
            modelId: $model->model_id,
            provider: Provider::from($model->provider),
            tier: ModelTier::from($model->tier),
            capabilities: new ModelCapabilities(
                structuredOutput: StructuredOutputCapability::from($model->structured_output),
                toolUse: ToolUseCapability::from($model->tool_use),
                streaming: StreamingCapability::from($model->streaming),
                vision: $model->vision,
                deterministicSeed: $model->deterministic_seed,
            ),
            contextWindow: $model->context_window,
            maxOutput: $model->max_output,
            pricing: new ModelPricing(
                inputPerMillionTokensUsd: $model->input_per_million_tokens_usd,
                outputPerMillionTokensUsd: $model->output_per_million_tokens_usd,
                cachedInputPerMillionTokensUsd: $model->cached_input_per_million_tokens_usd,
            ),
            status: ModelStatus::from($model->status),
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
