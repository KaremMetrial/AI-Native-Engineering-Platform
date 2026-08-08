<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * "Each tenant has an allowed-provider set, and routing never selects
 * outside it" (docs/architecture/ai/50-ai-platform-and-multi-provider.md,
 * D-559). One policy per tenant -- tenantId is the natural identity,
 * there is no separate surrogate id. A tenant with no policy row is
 * unrestricted (see SelectCandidateModels); this aggregate exists only
 * for tenants with an explicit, contractual restriction.
 *
 * No HTTP endpoint writes this aggregate in this pass -- see
 * app/AiOrchestration/README.md for why a real admin surface (Platform
 * Administration, C15) is deferred rather than guessed at here.
 */
final class TenantProviderPolicy
{
    /**
     * @param  list<Provider>  $allowedProviders
     */
    public function __construct(
        public readonly string $tenantId,
        private array $allowedProviders,
        public readonly DateTimeImmutable $createdAt,
    ) {
        if ($allowedProviders === []) {
            throw new InvalidArgumentException('A tenant provider policy must allow at least one provider.');
        }
    }

    /**
     * @param  list<Provider>  $allowedProviders
     */
    public static function restrict(string $tenantId, array $allowedProviders): self
    {
        return new self($tenantId, $allowedProviders, new DateTimeImmutable);
    }

    public function allows(Provider $provider): bool
    {
        return in_array($provider, $this->allowedProviders, true);
    }

    /**
     * @return list<Provider>
     */
    public function allowedProviders(): array
    {
        return $this->allowedProviders;
    }
}
