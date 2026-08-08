<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use App\Graph\Domain\Project;
use App\Graph\Domain\ProjectRepository;
use App\Graph\Domain\ProjectStatus;

class EloquentProjectRepository implements ProjectRepository
{
    public function save(Project $project): void
    {
        EloquentProject::query()->updateOrCreate(
            ['id' => $project->id],
            [
                'tenant_id' => $project->tenantId,
                'name' => $project->name(),
                'status' => $project->status()->value,
            ],
        );
    }

    public function findById(string $id): ?Project
    {
        $model = EloquentProject::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(EloquentProject $model): Project
    {
        return new Project(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            status: ProjectStatus::from($model->status),
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
