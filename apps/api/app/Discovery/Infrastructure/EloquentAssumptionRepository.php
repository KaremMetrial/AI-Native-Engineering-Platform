<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\Assumption;
use App\Discovery\Domain\AssumptionRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

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

    public function findAllBySession(string $sessionId): array
    {
        $rows = DB::table('discovery_assumptions')
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get();

        return array_values($rows->map(fn (mixed $row): Assumption => $this->rowToDomain($row))->all());
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

    private function rowToDomain(mixed $row): Assumption
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from discovery_assumptions.');
        }

        return new Assumption(
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
