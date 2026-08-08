<?php

declare(strict_types=1);

namespace App\Graph\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Separate aggregate from Artifact (D-335): the graph as a whole is not
 * an aggregate, or every write anywhere in a tenant's graph would
 * serialize against every other write. Links reference *versions*, not
 * artifacts (D-337) -- "derived from the approved version" must be
 * distinguishable from "derived from a superseded draft."
 */
final class ArtifactLink
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $fromVersionId,
        public readonly string $toVersionId,
        public readonly LinkType $linkType,
        public readonly string $createdBy,
        public readonly DateTimeImmutable $createdAt,
    ) {
        if ($fromVersionId === $toVersionId) {
            throw new DomainException('An artifact version cannot link to itself.');
        }
    }

    public static function create(
        string $id,
        string $tenantId,
        string $fromVersionId,
        string $toVersionId,
        LinkType $linkType,
        string $createdBy,
    ): self {
        return new self($id, $tenantId, $fromVersionId, $toVersionId, $linkType, $createdBy, new DateTimeImmutable);
    }
}
