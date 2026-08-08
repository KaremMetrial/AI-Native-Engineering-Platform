<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

enum StreamingCapability: string
{
    case None = 'none';
    case Text = 'text';
    case TextAndTools = 'text_and_tools';

    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Text => 1,
            self::TextAndTools => 2,
        };
    }
}
