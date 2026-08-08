<?php

declare(strict_types=1);

namespace App\Requirements\Infrastructure;

use App\Requirements\Domain\DocumentType;
use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Domain\RequirementDocumentRepository;
use App\Requirements\Domain\RequirementDocumentStatus;

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
}
