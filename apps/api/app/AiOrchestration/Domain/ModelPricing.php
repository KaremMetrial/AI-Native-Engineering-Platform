<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

use InvalidArgumentException;

/**
 * Input, output and **cached-input** rates tracked separately (D-553) --
 * cached reads are cheaper, and conflating them makes cost accounting
 * wrong. Value object on ModelRegistryEntry.
 */
final class ModelPricing
{
    public function __construct(
        public readonly float $inputPerMillionTokensUsd,
        public readonly float $outputPerMillionTokensUsd,
        public readonly float $cachedInputPerMillionTokensUsd,
    ) {
        if ($inputPerMillionTokensUsd < 0 || $outputPerMillionTokensUsd < 0 || $cachedInputPerMillionTokensUsd < 0) {
            throw new InvalidArgumentException('Model pricing rates cannot be negative.');
        }
    }
}
