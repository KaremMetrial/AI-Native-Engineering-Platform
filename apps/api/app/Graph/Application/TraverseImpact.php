<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\ArtifactVersionFinder;
use App\Graph\Domain\ImpactTraversal;
use RuntimeException;

/**
 * "What depends on this version?" -- the platform's differentiating
 * feature (docs/architecture/data/45-index-and-partitioning-strategy.md),
 * bounded to depth <= 5 (P-3, docs/architecture/04-non-functional-requirements.md).
 */
final class TraverseImpact
{
    private const MAX_DEPTH = 5;

    public function __construct(
        private readonly ArtifactVersionFinder $versions,
        private readonly ImpactTraversal $traversal,
    ) {}

    /**
     * @return list<string>
     */
    public function handle(string $startVersionId): array
    {
        if ($this->versions->findById($startVersionId) === null) {
            throw new RuntimeException('Artifact version not found in this tenant.');
        }

        return $this->traversal->traverse($startVersionId, self::MAX_DEPTH);
    }
}
