<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use App\AiOrchestration\Domain\ModelAdapter;
use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\ProviderNotConfigured;

/**
 * The default, and currently only, ModelAdapter binding. Its correctness
 * is that it fails loudly rather than faking a response -- no real
 * provider adapter exists in this codebase yet (see
 * app/AiOrchestration/README.md). Bound so the Gateway's dependency on
 * "an adapter" resolves to something, and so attempting to dispatch
 * before a real adapter is wired produces a clear, typed error instead of
 * a missing-binding exception or, worse, a silent fake success.
 */
final class NullModelAdapter implements ModelAdapter
{
    public function provider(): Provider
    {
        return Provider::Anthropic;
    }

    public function dispatch(string $modelId, string $prompt): string
    {
        throw ProviderNotConfigured::forProvider($this->provider());
    }
}
