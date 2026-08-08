<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

enum ToolUseCapability: string
{
    case None = 'none';
    case Sequential = 'sequential';
    case Parallel = 'parallel';

    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Sequential => 1,
            self::Parallel => 2,
        };
    }
}
