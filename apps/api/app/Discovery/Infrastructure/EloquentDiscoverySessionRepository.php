<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\DiscoverySessionRepository;
use App\Discovery\Domain\SessionStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

class EloquentDiscoverySessionRepository implements DiscoverySessionRepository
{
    public function save(DiscoverySession $session): void
    {
        EloquentDiscoverySession::query()->updateOrCreate(
            ['id' => $session->id],
            [
                'tenant_id' => $session->tenantId,
                'project_id' => $session->projectId,
                'title' => $session->title,
                'status' => $session->status()->value,
                'created_by' => $session->createdBy,
                'created_at' => $session->createdAt,
                'completed_at' => $session->completedAt(),
            ],
        );
    }

    public function findById(string $id): ?DiscoverySession
    {
        $model = EloquentDiscoverySession::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findAll(): array
    {
        // Plain query builder, not Eloquent -- without Larastan (D-206)
        // PHPStan's inference of chained Eloquent Builder generics is
        // unreliable; DB::table() has a single, well-typed stdClass-row
        // contract instead (see Graph's EloquentProjectRepository for the
        // same workaround).
        $rows = DB::table('discovery_sessions')->orderBy('created_at')->get();

        return array_values($rows->map(fn (mixed $row): DiscoverySession => $this->rowToDomain($row))->all());
    }

    private function toDomain(EloquentDiscoverySession $model): DiscoverySession
    {
        return new DiscoverySession(
            id: $model->id,
            tenantId: $model->tenant_id,
            projectId: $model->project_id,
            title: $model->title,
            status: SessionStatus::from($model->status),
            createdBy: $model->created_by,
            createdAt: $model->created_at->toDateTimeImmutable(),
            completedAt: $model->completed_at?->toDateTimeImmutable(),
        );
    }

    private function rowToDomain(mixed $row): DiscoverySession
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from discovery_sessions.');
        }

        $completedAt = $row->completed_at;

        return new DiscoverySession(
            id: $this->requireString($row->id),
            tenantId: $this->requireString($row->tenant_id),
            projectId: $this->requireString($row->project_id),
            title: $this->requireString($row->title),
            status: SessionStatus::from($this->requireString($row->status)),
            createdBy: $this->requireString($row->created_by),
            createdAt: new DateTimeImmutable($this->requireString($row->created_at)),
            completedAt: is_string($completedAt) ? new DateTimeImmutable($completedAt) : null,
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
