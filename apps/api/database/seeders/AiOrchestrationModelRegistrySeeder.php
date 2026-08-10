<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\AiOrchestration\Application\RegisterModel;
use App\AiOrchestration\Domain\ModelCapabilities;
use App\AiOrchestration\Domain\ModelPricing;
use App\AiOrchestration\Domain\ModelRegistryRepository;
use App\AiOrchestration\Domain\ModelTier;
use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;
use App\AiOrchestration\Infrastructure\EloquentModelRegistryEntry;
use Illuminate\Database\Seeder;

/**
 * D-280: the Model Registry is "configuration, reviewed like code, not
 * runtime-editable" (app/AiOrchestration/README.md, "No public write path
 * for the registry") -- entries are registered here, never through an
 * HTTP endpoint. Each entry is promoted straight to Active on seed: this
 * codebase has no conformance suite yet (see
 * ModelRegistryEntry::promote()'s docblock), so promotion here is a
 * deliberate configuration decision reviewed as part of this change, not
 * evidence of an automated conformance run.
 *
 * Idempotent by model_id so re-running `db:seed` against a database that
 * already has these entries is safe.
 */
class AiOrchestrationModelRegistrySeeder extends Seeder
{
    public function run(RegisterModel $registerModel, ModelRegistryRepository $models): void
    {
        foreach ($this->catalog() as $entry) {
            if (EloquentModelRegistryEntry::query()->where('model_id', $entry['model_id'])->exists()) {
                continue;
            }

            $registered = $registerModel->handle(
                modelId: $entry['model_id'],
                provider: $entry['provider'],
                tier: $entry['tier'],
                capabilities: $entry['capabilities'],
                contextWindow: $entry['context_window'],
                maxOutput: $entry['max_output'],
                pricing: $entry['pricing'],
            );

            $registered->promote();
            $models->save($registered);
        }
    }

    /**
     * @return list<array{
     *     model_id: string,
     *     provider: Provider,
     *     tier: ModelTier,
     *     capabilities: ModelCapabilities,
     *     context_window: int,
     *     max_output: int,
     *     pricing: ModelPricing,
     * }>
     */
    private function catalog(): array
    {
        return [
            [
                'model_id' => 'claude-opus-5',
                'provider' => Provider::Anthropic,
                'tier' => ModelTier::Frontier,
                'capabilities' => new ModelCapabilities(
                    structuredOutput: StructuredOutputCapability::StrictSchema,
                    toolUse: ToolUseCapability::Parallel,
                    streaming: StreamingCapability::TextAndTools,
                    vision: true,
                    deterministicSeed: false,
                ),
                'context_window' => 200000,
                'max_output' => 32000,
                'pricing' => new ModelPricing(
                    inputPerMillionTokensUsd: 15.0,
                    outputPerMillionTokensUsd: 75.0,
                    cachedInputPerMillionTokensUsd: 1.5,
                ),
            ],
            [
                'model_id' => 'claude-sonnet-5',
                'provider' => Provider::Anthropic,
                'tier' => ModelTier::Balanced,
                'capabilities' => new ModelCapabilities(
                    structuredOutput: StructuredOutputCapability::StrictSchema,
                    toolUse: ToolUseCapability::Parallel,
                    streaming: StreamingCapability::TextAndTools,
                    vision: true,
                    deterministicSeed: false,
                ),
                'context_window' => 200000,
                'max_output' => 16000,
                'pricing' => new ModelPricing(
                    inputPerMillionTokensUsd: 3.0,
                    outputPerMillionTokensUsd: 15.0,
                    cachedInputPerMillionTokensUsd: 0.3,
                ),
            ],
            [
                'model_id' => 'claude-haiku-4-5',
                'provider' => Provider::Anthropic,
                'tier' => ModelTier::Fast,
                'capabilities' => new ModelCapabilities(
                    structuredOutput: StructuredOutputCapability::JsonMode,
                    toolUse: ToolUseCapability::Sequential,
                    streaming: StreamingCapability::Text,
                    vision: true,
                    deterministicSeed: false,
                ),
                'context_window' => 200000,
                'max_output' => 8000,
                'pricing' => new ModelPricing(
                    inputPerMillionTokensUsd: 0.8,
                    outputPerMillionTokensUsd: 4.0,
                    cachedInputPerMillionTokensUsd: 0.08,
                ),
            ],
            [
                'model_id' => 'gpt-5',
                'provider' => Provider::OpenAi,
                'tier' => ModelTier::Frontier,
                'capabilities' => new ModelCapabilities(
                    structuredOutput: StructuredOutputCapability::StrictSchema,
                    toolUse: ToolUseCapability::Parallel,
                    streaming: StreamingCapability::TextAndTools,
                    vision: true,
                    deterministicSeed: false,
                ),
                'context_window' => 200000,
                'max_output' => 32000,
                'pricing' => new ModelPricing(
                    inputPerMillionTokensUsd: 1.25,
                    outputPerMillionTokensUsd: 10.0,
                    cachedInputPerMillionTokensUsd: 0.125,
                ),
            ],
        ];
    }
}
