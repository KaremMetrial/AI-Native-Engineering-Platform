<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

/**
 * D-94 (docs/architecture/ai/51-ai-gateway.md): routing quality-optimizes
 * within a tier before ever considering "always frontier."
 */
enum ModelTier: string
{
    case Fast = 'fast';
    case Balanced = 'balanced';
    case Frontier = 'frontier';
}
