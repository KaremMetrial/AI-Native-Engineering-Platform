<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\Constraint;
use App\Discovery\Domain\ConstraintRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

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

    public function findAllBySession(string $sessionId): array
    {
        $rows = DB::table('discovery_constraints')
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get();

        return array_values($rows->map(fn (mixed $row): Constraint => $this->rowToDomain($row))->all());
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

    private function rowToDomain(mixed $row): Constraint
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from discovery_constraints.');
        }

        return new Constraint(
            id: $this->requireString($row->id),
            tenantId: $this->requireString($row->tenant_id),
            sessionId: $this->requireString($row->session_id),
            statement: $this->requireString($row->statement),
            createdBy: $this->requireString($row->created_by),
            createdAt: new DateTimeImmutable($this->requireString($row->created_at)),
        );
    }

    private function requireString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected a string column value.');
        }

        return $value;
    }
}
