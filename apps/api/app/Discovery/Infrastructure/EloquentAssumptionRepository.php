<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\Assumption;
use App\Discovery\Domain\AssumptionRepository;

class EloquentAssumptionRepository implements AssumptionRepository
{
    public function save(Assumption $assumption): void
    {
        EloquentAssumption::query()->firstOrCreate(
            ['id' => $assumption->id],
            [
                'tenant_id' => $assumption->tenantId,
                'session_id' => $assumption->sessionId,
                'statement' => $assumption->statement,
                'created_by' => $assumption->createdBy,
                'created_at' => $assumption->createdAt,
            ],
        );
    }

    public function findById(string $id): ?Assumption
    {
        $model = EloquentAssumption::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(EloquentAssumption $model): Assumption
    {
        return new Assumption(
            id: $model->id,
            tenantId: $model->tenant_id,
            sessionId: $model->session_id,
            statement: $model->statement,
            createdBy: $model->created_by,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
