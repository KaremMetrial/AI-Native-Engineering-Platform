<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use App\Graph\Domain\Artifact;
use App\Graph\Domain\ArtifactRepository;
use App\Graph\Domain\ArtifactStatus;
use App\Graph\Domain\ArtifactVersion;
use App\Graph\Domain\Lineage;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * Persists the Artifact aggregate -- artifact row plus any versions not
 * yet saved -- as one transaction (D-333: one aggregate per transaction).
 * Versions are immutable and identified by id, so writing them is always
 * an insert-if-absent, never an update.
 */
class EloquentArtifactRepository implements ArtifactRepository
{
    public function save(Artifact $artifact): void
    {
        DB::transaction(function () use ($artifact): void {
            // current_version_id is set in a separate update below, after
            // the version rows exist: artifacts.current_version_id and
            // artifact_versions.artifact_id are mutual foreign keys
            // (database/migrations/2026_08_08_000003_graph_create_artifact_versions_table.php),
            // so the artifact row must exist before a version can
            // reference it, and the version must exist before the
            // artifact can point back to it.
            EloquentArtifact::query()->updateOrCreate(
                ['id' => $artifact->id],
                [
                    'tenant_id' => $artifact->tenantId,
                    'project_id' => $artifact->projectId,
                    'type' => $artifact->type,
                    'status' => $artifact->status()->value,
                ],
            );

            foreach ($artifact->versions() as $version) {
                EloquentArtifactVersion::query()->firstOrCreate(
                    ['id' => $version->id],
                    [
                        'tenant_id' => $artifact->tenantId,
                        'artifact_id' => $version->artifactId,
                        'version_number' => $version->versionNumber,
                        'content' => $version->content,
                        'lineage_model' => $version->lineage->model,
                        'lineage_prompt_version' => $version->lineage->promptVersion,
                        'lineage_input_version_ids' => $version->lineage->inputVersionIds,
                        'lineage_tokens' => $version->lineage->tokens,
                        'lineage_cost' => $version->lineage->cost,
                        'created_by' => $version->createdBy,
                        'created_at' => $version->createdAt,
                    ],
                );
            }

            EloquentArtifact::query()
                ->where('id', $artifact->id)
                ->update(['current_version_id' => $artifact->currentVersionId()]);
        });
    }

    public function findById(string $id): ?Artifact
    {
        $model = EloquentArtifact::query()->find($id);

        if ($model === null) {
            return null;
        }

        // Read via the plain query builder, not Eloquent, here: without
        // Larastan (D-206) PHPStan's inference of chained Eloquent Builder
        // generics is unreliable (observed resolving to stdClass on this
        // exact where()->orderBy()->get() shape, despite an identical
        // pattern elsewhere resolving differently) -- DB::table() has a
        // single, consistent, well-typed stdClass-row contract instead.
        $versionRows = DB::table('artifact_versions')
            ->where('artifact_id', $id)
            ->orderBy('version_number')
            ->get();

        $versions = [];
        foreach ($versionRows as $row) {
            $versions[] = $this->rowToVersion($row);
        }

        return new Artifact(
            id: $model->id,
            tenantId: $model->tenant_id,
            projectId: $model->project_id,
            type: $model->type,
            currentVersionId: $model->current_version_id,
            status: ArtifactStatus::from($model->status),
            createdAt: $model->created_at->toDateTimeImmutable(),
            versions: $versions,
        );
    }

    private function rowToVersion(mixed $row): ArtifactVersion
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from artifact_versions.');
        }

        return new ArtifactVersion(
            id: $this->requireString($row->id),
            artifactId: $this->requireString($row->artifact_id),
            versionNumber: $this->requireInt($row->version_number),
            content: $this->requireString($row->content),
            lineage: new Lineage(
                model: $this->optionalString($row->lineage_model),
                promptVersion: $this->optionalString($row->lineage_prompt_version),
                inputVersionIds: $this->stringListFromJson($row->lineage_input_version_ids),
                tokens: $this->optionalInt($row->lineage_tokens),
                cost: $this->optionalFloat($row->lineage_cost),
            ),
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

    private function requireInt(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new RuntimeException('Expected a numeric column value.');
        }

        return (int) $value;
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function optionalInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function optionalFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @return list<string>
     */
    private function stringListFromJson(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $strings = [];
        foreach ($decoded as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }
}
