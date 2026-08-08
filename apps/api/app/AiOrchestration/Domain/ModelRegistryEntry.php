<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * The Model Registry's single catalogue entry (docs/architecture/ai/50-ai-platform-and-multi-provider.md):
 * "the source of truth for routing." Registry entries are configuration,
 * reviewed like code, not runtime-editable (D-280) -- there is
 * deliberately no HTTP endpoint that writes this aggregate; it is
 * populated by RegisterModel from a seeder, the same way a config file
 * would be.
 */
final class ModelRegistryEntry
{
    public function __construct(
        public readonly string $id,
        public readonly string $modelId,
        public readonly Provider $provider,
        public readonly ModelTier $tier,
        public readonly ModelCapabilities $capabilities,
        public readonly int $contextWindow,
        public readonly int $maxOutput,
        public readonly ModelPricing $pricing,
        private ModelStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function register(
        string $id,
        string $modelId,
        Provider $provider,
        ModelTier $tier,
        ModelCapabilities $capabilities,
        int $contextWindow,
        int $maxOutput,
        ModelPricing $pricing,
    ): self {
        return new self($id, $modelId, $provider, $tier, $capabilities, $contextWindow, $maxOutput, $pricing, ModelStatus::Trial, new DateTimeImmutable);
    }

    /**
     * D-561: an adapter must pass the conformance suite before `active`.
     * This codebase has no conformance suite yet (see
     * app/AiOrchestration/README.md) -- calling this method is therefore
     * not itself evidence of conformance, only a state transition; the
     * process discipline lives outside the code today.
     */
    public function promote(): void
    {
        if ($this->status !== ModelStatus::Trial) {
            throw new DomainException("Cannot promote model [{$this->modelId}] to active from its current status.");
        }

        $this->status = ModelStatus::Active;
    }

    public function deprecate(): void
    {
        if ($this->status !== ModelStatus::Active) {
            throw new DomainException("Cannot deprecate model [{$this->modelId}] that is not active.");
        }

        $this->status = ModelStatus::Deprecated;
    }

    public function retire(): void
    {
        $this->status = ModelStatus::Retired;
    }

    public function status(): ModelStatus
    {
        return $this->status;
    }

    /**
     * The candidate-filter portion of routing (D-566). Deliberately does
     * not weigh a rendered prompt's actual token count against
     * contextWindow -- there is no Prompt Engine (`52`) yet to render one,
     * so this filters on the requirement's declared minimum only.
     */
    public function isEligibleFor(CapabilityRequirement $requirement): bool
    {
        return $this->status === ModelStatus::Active
            && $this->contextWindow >= $requirement->minContextWindow
            && $this->capabilities->satisfies($requirement);
    }
}
