<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use App\Graph\Domain\ArtifactVersion;
use App\Graph\Domain\ArtifactVersionFinder;
use App\Graph\Domain\Lineage;

class EloquentArtifactVersionFinder implements ArtifactVersionFinder
{
    public function findById(string $versionId): ?ArtifactVersion
    {
        $model = EloquentArtifactVersion::query()->find($versionId);

        if ($model === null) {
            return null;
        }

        return new ArtifactVersion(
            id: $model->id,
            artifactId: $model->artifact_id,
            versionNumber: $model->version_number,
            content: $model->content,
            lineage: new Lineage(
                model: $model->lineage_model,
                promptVersion: $model->lineage_prompt_version,
                inputVersionIds: $model->lineage_input_version_ids,
                tokens: $model->lineage_tokens,
                cost: $model->lineage_cost === null ? null : (float) $model->lineage_cost,
            ),
            createdBy: $model->created_by,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
