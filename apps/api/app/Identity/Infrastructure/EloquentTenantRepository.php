<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantRepository;
use App\Identity\Domain\TenantStatus;

class EloquentTenantRepository implements TenantRepository
{
    public function save(Tenant $tenant): void
    {
        EloquentTenant::query()->updateOrCreate(
            ['id' => $tenant->id],
            [
                'name' => $tenant->name(),
                'status' => $tenant->status()->value,
                'plan' => $tenant->plan,
                'region' => $tenant->region,
            ],
        );
    }

    public function findById(string $id): ?Tenant
    {
        $model = EloquentTenant::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(EloquentTenant $model): Tenant
    {
        return new Tenant(
            id: $model->id,
            name: $model->name,
            status: TenantStatus::from($model->status),
            plan: $model->plan,
            region: $model->region,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
