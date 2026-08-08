<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

/**
 * Ordered weakest-to-strongest by structural enforcement strength -- see
 * ModelCapabilities::satisfies() for why the ordering matters, not just
 * the labels. docs/architecture/ai/50-ai-platform-and-multi-provider.md
 * lists the four values without stating an explicit order; this ranking
 * (forced tool-call schema treated as the strongest enforcement, ahead of
 * native strict-schema mode) is this codebase's own interpretation,
 * documented here so a future reader can revise it deliberately rather
 * than rediscover it.
 */
enum StructuredOutputCapability: string
{
    case None = 'none';
    case JsonMode = 'json_mode';
    case StrictSchema = 'strict_schema';
    case ToolSchema = 'tool_schema';

    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::JsonMode => 1,
            self::StrictSchema => 2,
            self::ToolSchema => 3,
        };
    }
}
