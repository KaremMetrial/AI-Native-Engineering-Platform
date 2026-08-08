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
}
