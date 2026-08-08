<?php

declare(strict_types=1);

namespace Tests\Support;

use App\AiOrchestration\Domain\ModelCapabilities;
use App\AiOrchestration\Domain\ModelPricing;
use App\AiOrchestration\Domain\ModelRegistryEntry;
use App\AiOrchestration\Domain\ModelTier;
use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;
use App\AiOrchestration\Infrastructure\EloquentModelRegistryRepository;
use Illuminate\Support\Str;

/**
 * Shared fixture builders for AI Orchestration module tests. Reuses
 * CreatesGraphFixtures for tenant/user.
 */
trait CreatesAiOrchestrationFixtures
{
    use CreatesGraphFixtures;

    /**
     * Registers a model and, unless told otherwise, promotes it to
     * active -- most tests need an active candidate to select, and the
     * D-561 conformance-suite discipline this codebase hasn't built yet
     * is a process concern, not something the fixture needs to model.
     */
    private function registerModel(Provider $provider = Provider::Anthropic, bool $active = true): ModelRegistryEntry
    {
        $entry = ModelRegistryEntry::register(
            id: (string) Str::uuid(),
            modelId: 'test-model-'.Str::uuid(),
            provider: $provider,
            tier: ModelTier::Balanced,
            capabilities: new ModelCapabilities(
                structuredOutput: StructuredOutputCapability::StrictSchema,
                toolUse: ToolUseCapability::Parallel,
                streaming: StreamingCapability::TextAndTools,
                vision: true,
                deterministicSeed: true,
            ),
            contextWindow: 200000,
            maxOutput: 8192,
            pricing: new ModelPricing(3.0, 15.0, 0.3),
        );

        if ($active) {
            $entry->promote();
        }

        (new EloquentModelRegistryRepository)->save($entry);

        return $entry;
    }
}
