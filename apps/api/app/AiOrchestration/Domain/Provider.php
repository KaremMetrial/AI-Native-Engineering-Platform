<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

/**
 * The four providers named as first-class in the multi-provider decision
 * (docs/architecture/adr/0002-genuine-multi-provider-ai-support.md, D-550).
 * Adding a fifth requires the "Adding a Provider" process
 * (docs/architecture/ai/50-ai-platform-and-multi-provider.md), not just a
 * new enum case.
 */
enum Provider: string
{
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    case Google = 'google';
    case DeepSeek = 'deepseek';
}
