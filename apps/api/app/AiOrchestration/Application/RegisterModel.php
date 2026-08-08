<?php

declare(strict_types=1);

namespace App\AiOrchestration\Application;

use App\AiOrchestration\Domain\ModelCapabilities;
use App\AiOrchestration\Domain\ModelPricing;
use App\AiOrchestration\Domain\ModelRegistryEntry;
use App\AiOrchestration\Domain\ModelRegistryRepository;
use App\AiOrchestration\Domain\ModelTier;
use App\AiOrchestration\Domain\Provider;
use Illuminate\Support\Str;

/**
 * Called from a seeder, never from an HTTP controller -- the registry is
 * reviewed configuration, not runtime-editable (D-280,
 * app/AiOrchestration/Domain/ModelRegistryEntry.php docblock).
 */
final class RegisterModel
{
    public function __construct(
        private readonly ModelRegistryRepository $models,
    ) {}

    public function handle(
        string $modelId,
        Provider $provider,
        ModelTier $tier,
        ModelCapabilities $capabilities,
        int $contextWindow,
        int $maxOutput,
        ModelPricing $pricing,
    ): ModelRegistryEntry {
        $entry = ModelRegistryEntry::register(
            id: (string) Str::uuid(),
            modelId: $modelId,
            provider: $provider,
            tier: $tier,
            capabilities: $capabilities,
            contextWindow: $contextWindow,
            maxOutput: $maxOutput,
            pricing: $pricing,
        );

        $this->models->save($entry);

        return $entry;
    }
}
