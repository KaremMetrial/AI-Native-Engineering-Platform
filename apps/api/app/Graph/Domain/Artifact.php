<?php

declare(strict_types=1);

namespace App\Graph\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Aggregate root of the Delivery Graph kernel (docs/architecture/design/32-domain-model-and-ddd.md).
 * "A thin mutable pointer to the current version plus stable identity.
 * Nearly all the data lives in versions." Owns its ArtifactVersion child
 * entities as one aggregate -- creating a version is a write to this
 * aggregate, not a separate one (D-333/one-aggregate-per-transaction).
 *
 * ArtifactLink and Approval are deliberately separate aggregates (D-335)
 * and are not held here.
 */
final class Artifact
{
    /** @var list<ArtifactVersion> */
    private array $versions;

    /**
     * @param  list<ArtifactVersion>  $versions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $projectId,
        public readonly string $type,
        private ?string $currentVersionId,
        private ArtifactStatus $status,
        public readonly DateTimeImmutable $createdAt,
        array $versions = [],
    ) {
        $this->versions = $versions;
    }

    public static function create(
        string $id,
        string $tenantId,
        string $projectId,
        string $type,
        string $firstVersionId,
        string $content,
        string $createdBy,
    ): self {
        $artifact = new self(
            id: $id,
            tenantId: $tenantId,
            projectId: $projectId,
            type: $type,
            currentVersionId: null,
            status: ArtifactStatus::Active,
            createdAt: new DateTimeImmutable,
        );

        $artifact->recordNewVersion($firstVersionId, $content, Lineage::human(), $createdBy);

        return $artifact;
    }

    public function recordNewVersion(string $versionId, string $content, Lineage $lineage, string $createdBy): ArtifactVersion
    {
        if ($this->status === ArtifactStatus::Archived) {
            throw new DomainException('Cannot version an archived artifact.');
        }

        $version = new ArtifactVersion(
            id: $versionId,
            artifactId: $this->id,
            versionNumber: count($this->versions) + 1,
            content: $content,
            lineage: $lineage,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable,
        );

        $this->versions[] = $version;
        $this->currentVersionId = $version->id;

        return $version;
    }

    public function currentVersionId(): ?string
    {
        return $this->currentVersionId;
    }

    public function status(): ArtifactStatus
    {
        return $this->status;
    }

    /**
     * @return list<ArtifactVersion>
     */
    public function versions(): array
    {
        return $this->versions;
    }

    public function archive(): void
    {
        $this->status = ArtifactStatus::Archived;
    }
}
