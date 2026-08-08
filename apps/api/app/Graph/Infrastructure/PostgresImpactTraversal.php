<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use App\Graph\Domain\ImpactTraversal;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * The exact query shape docs/governance/22-graph-traversal-benchmark-results.md
 * measured (reverse recursive CTE over artifact_links, depth-bounded) and
 * docs/architecture/data/45-index-and-partitioning-strategy.md indexed
 * for (artifact_links_reverse_idx: tenant_id, to_version_id, link_type).
 * Runs under RLS as platform_app -- the tenant scope comes from the
 * bound session, not an explicit predicate here, exactly as production
 * traffic would run it.
 *
 * `DISTINCT` on the final select is the benchmark's own finding applied:
 * a hub-adjacent node reached through more than one path must be counted
 * once, not once per path.
 */
class PostgresImpactTraversal implements ImpactTraversal
{
    public function traverse(string $startVersionId, int $maxDepth): array
    {
        $rows = DB::select(
            <<<'SQL'
                WITH RECURSIVE impact(id, depth) AS (
                    SELECT id, 0 FROM artifact_versions WHERE id = ?
                    UNION
                    SELECT al.from_version_id, impact.depth + 1
                    FROM artifact_links al
                    JOIN impact ON al.to_version_id = impact.id
                    WHERE impact.depth < ?
                )
                SELECT DISTINCT id FROM impact WHERE id != ?
                SQL,
            [$startVersionId, $maxDepth, $startVersionId],
        );

        $ids = [];
        foreach ($rows as $row) {
            if (! $row instanceof stdClass || ! is_string($row->id)) {
                throw new RuntimeException('Expected a string id column from the traversal query.');
            }

            $ids[] = $row->id;
        }

        return $ids;
    }
}
