<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\TenantProviderPolicy;
use App\AiOrchestration\Domain\TenantProviderPolicyRepository;
use RuntimeException;

class EloquentTenantProviderPolicyRepository implements TenantProviderPolicyRepository
{
    public function save(TenantProviderPolicy $policy): void
    {
        EloquentTenantProviderPolicy::query()->updateOrCreate(
            ['tenant_id' => $policy->tenantId],
            [
                'allowed_providers' => array_map(
                    static fn (Provider $provider): string => $provider->value,
                    $policy->allowedProviders(),
                ),
                'created_at' => $policy->createdAt,
            ],
        );
    }

    public function findByTenant(string $tenantId): ?TenantProviderPolicy
    {
        $model = EloquentTenantProviderPolicy::query()->find($tenantId);

        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(EloquentTenantProviderPolicy $model): TenantProviderPolicy
    {
        return new TenantProviderPolicy(
            tenantId: $model->tenant_id,
            allowedProviders: array_map(
                fn (mixed $value): Provider => Provider::from($this->requireString($value)),
                $model->allowed_providers,
            ),
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }

    private function requireString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected each allowed provider entry to be a string.');
        }

        return $value;
    }
}
