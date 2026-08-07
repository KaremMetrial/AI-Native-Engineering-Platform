<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * Phase 0 graph-traversal benchmark (docs/governance/20-roadmap.md, TR-2,
 * P-3: "Graph traversal (impact analysis, depth <= 5), p95 < 1s").
 *
 * Seeds synthetic data into the throwaway `graph_poc_*` tables
 * (database/migrations/2026_08_07_000002_create_graph_poc_tables.php) and
 * benchmarks depth-bounded reverse recursive-CTE traversal ("what depends
 * on this?" -- the differentiating, harder direction per
 * docs/architecture/data/45-index-and-partitioning-strategy.md) under RLS,
 * exactly as it would run in production (D-54: benchmark with policies
 * enabled, never disabled for convenience).
 *
 * Re-runnable, not a one-time script: the risk register's mitigation for
 * TR-2 is "benchmark early" with signal "traversal p95 approaching the P-3
 * budget" -- this command is how that signal gets checked going forward.
 */
final class GraphPocBenchmark extends Command
{
    protected $signature = 'graph:poc-benchmark
        {--seed : (Re)seed synthetic data before benchmarking}
        {--depth=5 : Max traversal depth, matches P-3}
        {--samples=200 : Number of random starting nodes to time}';

    protected $description = 'Phase 0 spike: seed synthetic data and benchmark recursive-CTE graph traversal against P-3';

    private const BENCHMARK_TENANT = '11111111-1111-1111-1111-111111111111';

    private const NOISE_TENANT_COUNT = 200;

    private const NOISE_VERSIONS_PER_TENANT = 250;

    private const BENCHMARK_VERSION_COUNT = 10000;

    private const BENCHMARK_AVG_FAN_OUT = 4;

    private const HUB_NODE_COUNT = 20;

    private const HUB_FAN_OUT = 150;

    public function handle(): int
    {
        if ($this->option('seed')) {
            $this->seed();
        }

        $this->benchmark((int) $this->option('depth'), (int) $this->option('samples'));

        return self::SUCCESS;
    }

    private function seed(): void
    {
        $this->info('Seeding synthetic graph data (this truncates graph_poc_* first)...');

        DB::connection('pgsql_migrate')->statement('TRUNCATE graph_poc_links, graph_poc_versions RESTART IDENTITY CASCADE');

        // Noise tenants: proves the tenant_id-leading composite index keeps
        // the benchmark tenant's traversal from paying for unrelated
        // tenants' data sharing the same tables.
        $this->info(sprintf(
            'Seeding %d noise tenants x %d versions...',
            self::NOISE_TENANT_COUNT,
            self::NOISE_VERSIONS_PER_TENANT,
        ));

        DB::connection('pgsql_migrate')->statement(<<<'SQL'
            INSERT INTO graph_poc_versions (tenant_id, label, created_at, updated_at)
            SELECT
                ('00000000-0000-0000-0000-' || lpad(t::text, 12, '0'))::uuid,
                'noise-' || t || '-' || v,
                now(),
                now()
            FROM generate_series(1, ?) AS t
            CROSS JOIN generate_series(1, ?) AS v
            SQL, [self::NOISE_TENANT_COUNT, self::NOISE_VERSIONS_PER_TENANT]);

        DB::connection('pgsql_migrate')->statement(<<<'SQL'
            INSERT INTO graph_poc_links (tenant_id, from_version_id, to_version_id, link_type, created_at, updated_at)
            SELECT
                v.tenant_id,
                v.id,
                (SELECT id FROM graph_poc_versions v2
                    WHERE v2.tenant_id = v.tenant_id AND v2.id != v.id
                    ORDER BY random() LIMIT 1),
                'implements',
                now(),
                now()
            FROM graph_poc_versions v
            WHERE v.tenant_id != ?::uuid
            SQL, [self::BENCHMARK_TENANT]);

        // Benchmark tenant: a single large, dense subgraph -- a DAG (later
        // versions link back to earlier ones, so no cycle guard is needed
        // in the traversal query), with a handful of "hub" nodes carrying
        // deliberately high fan-in to stress the worst case the docs flag:
        // superlinear cost growth with depth when fan-out is high.
        $this->info(sprintf(
            'Seeding benchmark tenant: %d versions, ~%d avg fan-out, %d hub nodes x %d fan-out...',
            self::BENCHMARK_VERSION_COUNT,
            self::BENCHMARK_AVG_FAN_OUT,
            self::HUB_NODE_COUNT,
            self::HUB_FAN_OUT,
        ));

        DB::connection('pgsql_migrate')->statement(<<<'SQL'
            INSERT INTO graph_poc_versions (tenant_id, label, created_at, updated_at)
            SELECT ?::uuid, 'bench-' || v, now(), now()
            FROM generate_series(1, ?) AS v
            SQL, [self::BENCHMARK_TENANT, self::BENCHMARK_VERSION_COUNT]);

        $minId = $this->expectNumericProperty(
            DB::connection('pgsql_migrate')->selectOne(
                'SELECT min(id) AS id FROM graph_poc_versions WHERE tenant_id = ?::uuid',
                [self::BENCHMARK_TENANT],
            ),
            'id',
        );

        // Regular fan-out: each version (past the first 10% used as roots)
        // links to a few earlier versions.
        DB::connection('pgsql_migrate')->statement(<<<'SQL'
            INSERT INTO graph_poc_links (tenant_id, from_version_id, to_version_id, link_type, created_at, updated_at)
            SELECT
                ?::uuid,
                v.id,
                ? + floor(random() * greatest(v.id - ? - 1, 1))::int,
                'implements',
                now(),
                now()
            FROM graph_poc_versions v
            CROSS JOIN generate_series(1, ?) AS fanout
            WHERE v.tenant_id = ?::uuid
              AND v.id > ? + (? / 10)
            SQL, [
            self::BENCHMARK_TENANT,
            $minId, $minId,
            self::BENCHMARK_AVG_FAN_OUT,
            self::BENCHMARK_TENANT,
            $minId, self::BENCHMARK_VERSION_COUNT,
        ]);

        // Hub nodes: a handful of early, foundational versions that a large
        // number of later versions depend on -- the realistic worst case
        // for reverse ("what depends on this?") traversal.
        DB::connection('pgsql_migrate')->statement(<<<'SQL'
            INSERT INTO graph_poc_links (tenant_id, from_version_id, to_version_id, link_type, created_at, updated_at)
            SELECT
                ?::uuid,
                ? + floor(random() * (? / 5))::int,
                hub.hub_id,
                'implements',
                now(),
                now()
            FROM (SELECT ? + gs AS hub_id FROM generate_series(0, ? - 1) AS gs) hub
            CROSS JOIN generate_series(1, ?) AS fanout
            SQL, [
            self::BENCHMARK_TENANT,
            $minId, self::BENCHMARK_VERSION_COUNT,
            $minId, self::HUB_NODE_COUNT,
            self::HUB_FAN_OUT,
        ]);

        $versionCount = $this->expectNumericProperty(
            DB::connection('pgsql_migrate')->selectOne('SELECT count(*) AS versions FROM graph_poc_versions'),
            'versions',
        );
        $linkCount = $this->expectNumericProperty(
            DB::connection('pgsql_migrate')->selectOne('SELECT count(*) AS links FROM graph_poc_links'),
            'links',
        );
        $this->info(sprintf('Seeded %d versions, %d links total.', $versionCount, $linkCount));
    }

    /**
     * DB facade calls resolve to `mixed` without Laravel-aware static
     * analysis stubs (the tracked Larastan gap, D-206, tools/phpstan/README.md)
     * -- this narrows the runtime shape explicitly rather than casting
     * mixed away.
     */
    private function expectNumericProperty(mixed $row, string $property): int
    {
        if (! $row instanceof stdClass || ! isset($row->{$property}) || ! is_numeric($row->{$property})) {
            throw new RuntimeException("Expected a numeric [{$property}] in the query result.");
        }

        return (int) $row->{$property};
    }

    private function benchmark(int $depth, int $samples): void
    {
        $tenantContext = new TenantContext;
        $tenantContext->bind(self::BENCHMARK_TENANT);

        $startIds = DB::connection('pgsql')
            ->table('graph_poc_versions')
            ->inRandomOrder()
            ->limit($samples)
            ->pluck('id');

        if ($startIds->isEmpty()) {
            $this->error('No benchmark-tenant data found. Run with --seed first.');

            return;
        }

        $this->info(sprintf('Running %d traversal samples at depth %d (RLS enabled, platform_app role)...', $startIds->count(), $depth));

        $query = <<<'SQL'
            WITH RECURSIVE impact(id, depth) AS (
                SELECT id, 0 FROM graph_poc_versions WHERE id = ?
                UNION ALL
                SELECT gl.from_version_id, impact.depth + 1
                FROM graph_poc_links gl
                JOIN impact ON gl.to_version_id = impact.id
                WHERE impact.depth < ?
            )
            SELECT count(*) AS reached FROM impact
            SQL;

        $timingsMs = [];
        $maxReached = 0;

        foreach ($startIds as $startId) {
            $start = hrtime(true);
            $result = DB::connection('pgsql')->selectOne($query, [$startId, $depth]);
            $elapsedMs = (hrtime(true) - $start) / 1_000_000;

            $timingsMs[] = $elapsedMs;
            $maxReached = max($maxReached, $this->expectNumericProperty($result, 'reached'));
        }

        sort($timingsMs);
        $n = count($timingsMs);
        $p50 = $timingsMs[(int) floor($n * 0.50)];
        $p95 = $timingsMs[(int) min($n - 1, floor($n * 0.95))];
        $p99 = $timingsMs[(int) min($n - 1, floor($n * 0.99))];
        $max = $timingsMs[$n - 1];

        $firstStartId = $startIds->first();
        if (! is_int($firstStartId) && ! is_string($firstStartId)) {
            throw new RuntimeException('Expected a scalar id from graph_poc_versions.');
        }

        $explainSql = sprintf(
            <<<'SQL'
                EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
                WITH RECURSIVE impact(id, depth) AS (
                    SELECT id, 0 FROM graph_poc_versions WHERE id = %d
                    UNION ALL
                    SELECT gl.from_version_id, impact.depth + 1
                    FROM graph_poc_links gl
                    JOIN impact ON gl.to_version_id = impact.id
                    WHERE impact.depth < %d
                )
                SELECT count(*) AS reached FROM impact
                SQL,
            (int) $firstStartId,
            $depth,
        );

        $explain = DB::connection('pgsql')->select($explainSql);

        $tenantContext->clear();

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Samples', $n],
            ['Max nodes reached (single traversal)', $maxReached],
            ['p50 (ms)', round($p50, 2)],
            ['p95 (ms)', round($p95, 2)],
            ['p99 (ms)', round($p99, 2)],
            ['max (ms)', round($max, 2)],
            ['P-3 budget', 'p95 < 1000ms'],
            ['Result', $p95 < 1000 ? 'PASS' : 'FAIL'],
        ]);

        $this->newLine();
        $this->line('Sample EXPLAIN ANALYZE plan (first sampled node):');
        foreach ($explain as $row) {
            if (! $row instanceof stdClass || ! is_string($row->{'QUERY PLAN'} ?? null)) {
                continue;
            }

            $this->line($row->{'QUERY PLAN'});
        }
    }
}
