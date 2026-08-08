<?php

declare(strict_types=1);

namespace App\Requirements\Infrastructure;

use App\Requirements\Domain\AcceptanceCriterion;
use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementRepository;
use App\Requirements\Domain\RequirementStatus;
use RuntimeException;

class EloquentRequirementRepository implements RequirementRepository
{
    public function save(Requirement $requirement): void
    {
        EloquentRequirement::query()->updateOrCreate(
            ['id' => $requirement->id],
            [
                'tenant_id' => $requirement->tenantId,
                'document_id' => $requirement->documentId,
                'text' => $requirement->text,
                'acceptance_criteria' => array_map(
                    static fn (AcceptanceCriterion $criterion): array => ['description' => $criterion->description],
                    $requirement->acceptanceCriteria(),
                ),
                'status' => $requirement->status()->value,
                'created_by' => $requirement->createdBy,
                'created_at' => $requirement->createdAt,
            ],
        );
    }

    public function findById(string $id): ?Requirement
    {
        $model = EloquentRequirement::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function countByDocument(string $documentId): int
    {
        return EloquentRequirement::query()->where('document_id', $documentId)->count();
    }

    public function countApprovedByDocument(string $documentId): int
    {
        return EloquentRequirement::query()
            ->where('document_id', $documentId)
            ->where('status', RequirementStatus::Approved->value)
            ->count();
    }

    private function toDomain(EloquentRequirement $model): Requirement
    {
        return new Requirement(
            id: $model->id,
            tenantId: $model->tenant_id,
            documentId: $model->document_id,
            text: $model->text,
            acceptanceCriteria: array_map(
                fn (mixed $row): AcceptanceCriterion => new AcceptanceCriterion($this->requireDescription($row)),
                $model->acceptance_criteria,
            ),
            status: RequirementStatus::from($model->status),
            createdBy: $model->created_by,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }

    private function requireDescription(mixed $row): string
    {
        if (! is_array($row) || ! isset($row['description']) || ! is_string($row['description'])) {
            throw new RuntimeException('Expected an acceptance criterion row with a string [description].');
        }

        return $row['description'];
    }
}
