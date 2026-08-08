<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

/**
 * Deliberately stops at `Selected`, not `Dispatched`/`Completed`/`Failed`-
 * after-dispatch -- this codebase has no real ModelAdapter implementation
 * yet (see NullModelAdapter and app/AiOrchestration/README.md), so there
 * is no dispatch step to model the terminal states of.
 */
enum GenerationStatus: string
{
    case Queued = 'queued';
    case Selected = 'selected';
    case Failed = 'failed';
}
