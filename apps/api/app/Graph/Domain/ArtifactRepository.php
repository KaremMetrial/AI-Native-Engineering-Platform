<?php

declare(strict_types=1);

namespace App\Graph\Domain;

interface ArtifactRepository
{
    /**
     * Persists the artifact and any versions not yet saved. One aggregate
     * per transaction (D-333): this call is the only write path for both
     * the artifact and its versions.
     */
    public function save(Artifact $artifact): void;

    public function findById(string $id): ?Artifact;

    /**
     * Every artifact in a project, each with its full version history --
     * a project workspace needs both in the same view, and this avoids an
     * N+1 by batching the version fetch (see EloquentArtifactRepository).
     *
     * @return list<Artifact>
     */
    public function findAllForProject(string $projectId): array;
}
