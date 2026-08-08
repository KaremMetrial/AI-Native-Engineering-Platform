<?php

declare(strict_types=1);

namespace App\Graph\Domain;

interface ArtifactLinkRepository
{
    public function save(ArtifactLink $link): void;

    public function findById(string $id): ?ArtifactLink;

    /**
     * Reverse traversal, one hop: everything that links *to* this
     * version -- "what depends on this?" (docs/architecture/data/45-index-and-partitioning-strategy.md,
     * the harder, product-differentiating direction).
     *
     * @return list<ArtifactLink>
     */
    public function findByToVersion(string $versionId): array;
}
