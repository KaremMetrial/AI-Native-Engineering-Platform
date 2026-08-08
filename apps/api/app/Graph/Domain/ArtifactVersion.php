<?php

declare(strict_types=1);

namespace App\Graph\Domain;

use DateTimeImmutable;

/**
 * Immutable child entity of the Artifact aggregate (D-336: "an edit
 * creates a new version; there is no update path"). Not an independent
 * aggregate -- it has no repository of its own for writes, only for the
 * read-side lookups ArtifactLink and Approval need to validate a version
 * id exists (see ArtifactVersionFinder).
 */
final class ArtifactVersion
{
    public function __construct(
        public readonly string $id,
        public readonly string $artifactId,
        public readonly int $versionNumber,
        public readonly string $content,
        public readonly Lineage $lineage,
        public readonly string $createdBy,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}
