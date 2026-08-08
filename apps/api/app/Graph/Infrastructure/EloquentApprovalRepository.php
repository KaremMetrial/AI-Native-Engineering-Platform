<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use App\Graph\Domain\Approval;
use App\Graph\Domain\ApprovalDecision;
use App\Graph\Domain\ApprovalRepository;

class EloquentApprovalRepository implements ApprovalRepository
{
    public function save(Approval $approval): void
    {
        EloquentApproval::query()->firstOrCreate(
            ['id' => $approval->id],
            [
                'tenant_id' => $approval->tenantId,
                'artifact_version_id' => $approval->artifactVersionId,
                'approved_by' => $approval->approvedBy,
                'decision' => $approval->decision->value,
                'comment' => $approval->comment,
                'created_at' => $approval->createdAt,
            ],
        );
    }

    public function findByArtifactVersion(string $artifactVersionId): array
    {
        return array_values(
            EloquentApproval::query()
                ->where('artifact_version_id', $artifactVersionId)
                ->get()
                ->map(fn (EloquentApproval $m): Approval => $this->toDomain($m))
                ->all(),
        );
    }

    private function toDomain(EloquentApproval $model): Approval
    {
        return new Approval(
            id: $model->id,
            tenantId: $model->tenant_id,
            artifactVersionId: $model->artifact_version_id,
            approvedBy: $model->approved_by,
            decision: ApprovalDecision::from($model->decision),
            comment: $model->comment,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
