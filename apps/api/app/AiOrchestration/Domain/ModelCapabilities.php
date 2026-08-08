<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

/**
 * What a registered model provides (docs/architecture/ai/50-ai-platform-and-multi-provider.md,
 * Capability Modelling). Value object on ModelRegistryEntry -- same
 * reasoning as Lineage on ArtifactVersion (app/Graph/Domain/Lineage.php):
 * no identity of its own, meaningless apart from the model it describes.
 */
final class ModelCapabilities
{
    public function __construct(
        public readonly StructuredOutputCapability $structuredOutput,
        public readonly ToolUseCapability $toolUse,
        public readonly StreamingCapability $streaming,
        public readonly bool $vision = false,
        public readonly bool $deterministicSeed = false,
    ) {}

    /**
     * "Capability degradation is explicit, never silent" (D-555) --
     * this is the predicate the gateway's filter stage (D-566) uses to
     * decide eligibility. Requiring a capability this model lacks means
     * "not a candidate," not "substitute the closest thing."
     */
    public function satisfies(CapabilityRequirement $requirement): bool
    {
        if ($this->structuredOutput->rank() < $requirement->structuredOutput->rank()) {
            return false;
        }

        if ($this->toolUse->rank() < $requirement->toolUse->rank()) {
            return false;
        }

        if ($this->streaming->rank() < $requirement->streaming->rank()) {
            return false;
        }

        if ($requirement->requiresVision && ! $this->vision) {
            return false;
        }

        if ($requirement->requiresDeterministicSeed && ! $this->deterministicSeed) {
            return false;
        }

        return true;
    }
}
