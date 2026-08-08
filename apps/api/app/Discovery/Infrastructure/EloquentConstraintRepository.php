<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\Constraint;
use App\Discovery\Domain\ConstraintRepository;

class EloquentConstraintRepository implements ConstraintRepository
{
    public function save(Constraint $constraint): void
    {
        EloquentConstraint::query()->firstOrCreate(
            ['id' => $constraint->id],
            [
                'tenant_id' => $constraint->tenantId,
                'session_id' => $constraint->sessionId,
                'statement' => $constraint->statement,
                'created_by' => $constraint->createdBy,
                'created_at' => $constraint->createdAt,
            ],
        );
    }

    public function findById(string $id): ?Constraint
    {
        $model = EloquentConstraint::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(EloquentConstraint $model): Constraint
    {
        return new Constraint(
            id: $model->id,
            tenantId: $model->tenant_id,
            sessionId: $model->session_id,
            statement: $model->statement,
            createdBy: $model->created_by,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
