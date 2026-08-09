<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use App\Graph\Domain\Project;
use App\Graph\Domain\ProjectRepository;
use App\Graph\Domain\ProjectStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

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

    public function findAll(): array
    {
        // Plain query builder, not Eloquent -- without Larastan (D-206)
        // PHPStan's inference of chained Eloquent Builder generics is
        // unreliable (see EloquentArtifactRepository::findById for the
        // same workaround); DB::table() has a single, well-typed
        // stdClass-row contract instead.
        $rows = DB::table('projects')->orderBy('created_at')->get();

        return array_values(
            $rows->map(fn (mixed $row): Project => $this->rowToDomain($row))->all(),
        );
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

    private function rowToDomain(mixed $row): Project
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from projects.');
        }

        return new Project(
            id: $this->requireString($row->id),
            tenantId: $this->requireString($row->tenant_id),
            name: $this->requireString($row->name),
            status: ProjectStatus::from($this->requireString($row->status)),
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
