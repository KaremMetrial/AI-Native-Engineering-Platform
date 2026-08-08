<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use App\AiOrchestration\Domain\GenerationDispatcher;

class QueuedGenerationDispatcher implements GenerationDispatcher
{
    public function dispatch(string $generationRecordId): void
    {
        ProcessGenerationRequest::dispatch($generationRecordId);
    }
}
