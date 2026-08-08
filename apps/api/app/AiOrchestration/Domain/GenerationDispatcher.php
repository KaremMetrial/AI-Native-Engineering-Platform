<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

/**
 * The Application layer's port onto "queue the work" (P-9) -- kept as a
 * Domain-owned interface, the same way repositories are, so
 * RequestGeneration depends on an abstraction rather than reaching into
 * Infrastructure's concrete queue Job class. Implemented by
 * QueuedGenerationDispatcher.
 */
interface GenerationDispatcher
{
    public function dispatch(string $generationRecordId): void;
}
