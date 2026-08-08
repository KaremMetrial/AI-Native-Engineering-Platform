<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

/**
 * docs/architecture/ai/50-ai-platform-and-multi-provider.md, Model
 * Registry table. Only `Active` models are candidates for routing
 * (D-566) -- a model reaches `Active` only after its adapter passes the
 * conformance suite (D-561), which this codebase has not built yet (see
 * app/AiOrchestration/README.md).
 */
enum ModelStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Deprecated = 'deprecated';
    case Retired = 'retired';
}
