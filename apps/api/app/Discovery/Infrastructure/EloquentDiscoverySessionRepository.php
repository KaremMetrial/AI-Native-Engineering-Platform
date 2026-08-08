<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\DiscoverySessionRepository;
use App\Discovery\Domain\SessionStatus;

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
}
