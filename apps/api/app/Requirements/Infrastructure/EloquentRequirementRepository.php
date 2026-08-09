<?php

declare(strict_types=1);

namespace App\Requirements\Infrastructure;

use App\Requirements\Domain\AcceptanceCriterion;
use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementRepository;
use App\Requirements\Domain\RequirementStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

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

    public function findAllByDocument(string $documentId): array
    {
        // Plain query builder, not Eloquent -- see findById's sibling
        // methods on Graph/Discovery for the same PHPStan-generics
        // rationale. Unlike Eloquent, the query builder doesn't apply the
        // model's `acceptance_criteria` array cast, so the JSON column
        // comes back as a raw string here and is decoded explicitly.
        $rows = DB::table('requirements')
            ->where('document_id', $documentId)
            ->orderBy('created_at')
            ->get();

        return array_values($rows->map(fn (mixed $row): Requirement => $this->rowToDomain($row))->all());
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

    private function rowToDomain(mixed $row): Requirement
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from requirements.');
        }

        return new Requirement(
            id: $this->requireString($row->id),
            tenantId: $this->requireString($row->tenant_id),
            documentId: $this->requireString($row->document_id),
            text: $this->requireString($row->text),
            acceptanceCriteria: array_map(
                fn (mixed $criterionRow): AcceptanceCriterion => new AcceptanceCriterion($this->requireDescription($criterionRow)),
                $this->decodeAcceptanceCriteria($row->acceptance_criteria),
            ),
            status: RequirementStatus::from($this->requireString($row->status)),
            createdBy: $this->requireString($row->created_by),
            createdAt: new DateTimeImmutable($this->requireString($row->created_at)),
        );
    }

    /**
     * @return list<mixed>
     */
    private function decodeAcceptanceCriteria(mixed $value): array
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected a JSON string acceptance_criteria column value.');
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Expected acceptance_criteria to decode to an array.');
        }

        return array_values($decoded);
    }

    private function requireString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected a string column value.');
        }

        return $value;
    }

    private function requireDescription(mixed $row): string
    {
        if (! is_array($row) || ! isset($row['description']) || ! is_string($row['description'])) {
            throw new RuntimeException('Expected an acceptance criterion row with a string [description].');
        }

        return $row['description'];
    }
}
