<?php

declare(strict_types=1);

namespace App\Requirements\Infrastructure;

use App\Requirements\Domain\DocumentType;
use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Domain\RequirementDocumentRepository;
use App\Requirements\Domain\RequirementDocumentStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

class EloquentRequirementDocumentRepository implements RequirementDocumentRepository
{
    public function save(RequirementDocument $document): void
    {
        EloquentRequirementDocument::query()->updateOrCreate(
            ['id' => $document->id],
            [
                'tenant_id' => $document->tenantId,
                'project_id' => $document->projectId,
                'type' => $document->type->value,
                'title' => $document->title,
                'status' => $document->status()->value,
                'created_by' => $document->createdBy,
                'created_at' => $document->createdAt,
            ],
        );
    }

    public function findById(string $id): ?RequirementDocument
    {
        $model = EloquentRequirementDocument::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findAll(): array
    {
        // Plain query builder, not Eloquent -- without Larastan (D-206)
        // PHPStan's inference of chained Eloquent Builder generics is
        // unreliable; DB::table() has a single, well-typed stdClass-row
        // contract instead (see Graph's EloquentProjectRepository for the
        // same workaround).
        $rows = DB::table('requirement_documents')->orderBy('created_at')->get();

        return array_values($rows->map(fn (mixed $row): RequirementDocument => $this->rowToDomain($row))->all());
    }

    private function toDomain(EloquentRequirementDocument $model): RequirementDocument
    {
        return new RequirementDocument(
            id: $model->id,
            tenantId: $model->tenant_id,
            projectId: $model->project_id,
            type: DocumentType::from($model->type),
            title: $model->title,
            status: RequirementDocumentStatus::from($model->status),
            createdBy: $model->created_by,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }

    private function rowToDomain(mixed $row): RequirementDocument
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from requirement_documents.');
        }

        return new RequirementDocument(
            id: $this->requireString($row->id),
            tenantId: $this->requireString($row->tenant_id),
            projectId: $this->requireString($row->project_id),
            type: DocumentType::from($this->requireString($row->type)),
            title: $this->requireString($row->title),
            status: RequirementDocumentStatus::from($this->requireString($row->status)),
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
