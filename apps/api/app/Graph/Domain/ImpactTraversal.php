<?php

declare(strict_types=1);

namespace App\Graph\Domain;

/**
 * Reverse impact analysis ("what depends on this version?"), the query
 * docs/governance/22-graph-traversal-benchmark-results.md measured and
 * docs/architecture/data/45-index-and-partitioning-strategy.md indexed
 * for. A query concern, not an aggregate concern
 * (docs/architecture/design/32-domain-model-and-ddd.md) -- implemented
 * directly against the database rather than through ArtifactLinkRepository,
 * because repeated single-hop repository calls would be the exact N+1
 * pattern the benchmark's recursive CTE exists to avoid.
 */
interface ImpactTraversal
{
    /**
     * @return list<string> distinct artifact version ids reached, deduplicated
     *                      across paths (the benchmark's own finding: a naive UNION ALL
     *                      over-counts through hub nodes reached by more than one path)
     */
    public function traverse(string $startVersionId, int $maxDepth): array;
}
