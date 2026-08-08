<?php

declare(strict_types=1);

namespace App\Graph\Domain;

/**
 * Read-side lookup for a single version, independent of loading its
 * parent Artifact aggregate. ArtifactLink and Approval reference versions
 * by plain id (they are separate aggregates, D-335) and need to validate
 * a version exists and belongs to the acting tenant without pulling in
 * the whole version history.
 */
interface ArtifactVersionFinder
{
    public function findById(string $versionId): ?ArtifactVersion;
}
