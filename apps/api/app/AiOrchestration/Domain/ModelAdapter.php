<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

/**
 * The dispatch-stage contract (docs/architecture/ai/51-ai-gateway.md,
 * stage 8) every provider adapter will implement. Deliberately minimal --
 * the full normalized request/response shape doc 50's normalization table
 * describes (system prompt placement, tool schemas, stop reasons,
 * streaming events, cached-token accounting) has no consumer to inform
 * its real shape yet: no Prompt Engine (`52`) renders a prompt, and no
 * concrete adapter exists to normalize against (see
 * app/AiOrchestration/README.md). This interface exists so the Gateway's
 * dependency on "an adapter" is real and type-checked now, without
 * guessing at a richer contract no code yet needs.
 */
interface ModelAdapter
{
    public function provider(): Provider;

    public function dispatch(string $modelId, string $prompt): string;
}
