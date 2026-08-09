<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use App\Graph\Domain\ArtifactLink;
use App\Graph\Domain\ArtifactLinkRepository;
use App\Graph\Domain\LinkType;

class EloquentArtifactLinkRepository implements ArtifactLinkRepository
{
    public function save(ArtifactLink $link): void
    {
        EloquentArtifactLink::query()->firstOrCreate(
            ['id' => $link->id],
            [
                'tenant_id' => $link->tenantId,
                'from_version_id' => $link->fromVersionId,
                'to_version_id' => $link->toVersionId,
                'link_type' => $link->linkType->value,
                'created_by' => $link->createdBy,
                'created_at' => $link->createdAt,
            ],
        );
    }

    public function findById(string $id): ?ArtifactLink
    {
        $model = EloquentArtifactLink::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByToVersion(string $versionId): array
    {
        return array_values(
            EloquentArtifactLink::query()
                ->where('to_version_id', $versionId)
                ->get()
                ->map(fn (EloquentArtifactLink $m): ArtifactLink => $this->toDomain($m))
                ->all(),
        );
    }

    public function findByFromVersion(string $versionId): array
    {
        return array_values(
            EloquentArtifactLink::query()
                ->where('from_version_id', $versionId)
                ->get()
                ->map(fn (EloquentArtifactLink $m): ArtifactLink => $this->toDomain($m))
                ->all(),
        );
    }

    private function toDomain(EloquentArtifactLink $model): ArtifactLink
    {
        return new ArtifactLink(
            id: $model->id,
            tenantId: $model->tenant_id,
            fromVersionId: $model->from_version_id,
            toVersionId: $model->to_version_id,
            linkType: LinkType::from($model->link_type),
            createdBy: $model->created_by,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
