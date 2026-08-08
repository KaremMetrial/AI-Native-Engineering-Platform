<?php

declare(strict_types=1);

namespace App\AiOrchestration\Application;

use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\TenantProviderPolicy;
use App\AiOrchestration\Domain\TenantProviderPolicyRepository;

/**
 * Called from tests and internal tooling, never from an HTTP controller
 * in this pass -- see app/AiOrchestration/README.md for why a real admin
 * surface (Platform Administration, C15) is deferred rather than guessed
 * at here.
 */
final class SetTenantProviderPolicy
{
    public function __construct(
        private readonly TenantProviderPolicyRepository $policies,
    ) {}

    /**
     * @param  list<Provider>  $allowedProviders
     */
    public function handle(string $tenantId, array $allowedProviders): TenantProviderPolicy
    {
        $policy = TenantProviderPolicy::restrict($tenantId, $allowedProviders);

        $this->policies->save($policy);

        return $policy;
    }
}
