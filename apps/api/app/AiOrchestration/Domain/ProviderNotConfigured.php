<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

use DomainException;

/**
 * Named for the violated rule (docs/foundation/25-naming-standards.md).
 * Thrown by NullModelAdapter -- the honest, correct behavior of "no real
 * provider adapter is wired yet" is to fail loudly on dispatch, never to
 * fake a response (docs/engineering/MASTER_SYSTEM_PROMPT.md: "Never fake
 * implementations").
 */
final class ProviderNotConfigured extends DomainException
{
    public static function forProvider(Provider $provider): self
    {
        return new self("No adapter is configured for provider [{$provider->value}].");
    }
}
