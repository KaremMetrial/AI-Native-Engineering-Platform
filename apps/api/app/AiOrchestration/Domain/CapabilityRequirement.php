<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

/**
 * What a workflow declares it needs (docs/architecture/ai/50-ai-platform-and-multi-provider.md:
 * "Workflows declare the capabilities they require; the registry declares
 * what each model provides; the gateway matches. Workflows never name a
 * model.", D-554). Value object -- no identity, meaningless apart from
 * the request it describes.
 *
 * Deliberately a subset of the full capability descriptor table: prompt
 * caching, reasoning mode and system-prompt placement are gateway/adapter
 * normalization concerns (how a call is shaped), not candidate-filtering
 * concerns (which models are eligible) -- and normalization has no
 * consumer yet since the Prompt Engine (`52`) does not exist.
 */
final class CapabilityRequirement
{
    public function __construct(
        public readonly StructuredOutputCapability $structuredOutput,
        public readonly ToolUseCapability $toolUse,
        public readonly StreamingCapability $streaming,
        public readonly int $minContextWindow,
        public readonly bool $requiresVision = false,
        public readonly bool $requiresDeterministicSeed = false,
    ) {}
}
