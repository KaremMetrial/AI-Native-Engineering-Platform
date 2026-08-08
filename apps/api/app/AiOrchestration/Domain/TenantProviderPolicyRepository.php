<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

interface TenantProviderPolicyRepository
{
    public function save(TenantProviderPolicy $policy): void;

    public function findByTenant(string $tenantId): ?TenantProviderPolicy;
}
