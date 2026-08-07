# Graph Traversal Benchmark Results

## Purpose

Record the result of the Phase 0 "Graph traversal benchmark" deliverable
(`20-roadmap.md`), which resolves TR-2 (`19-risk-register.md`) — the
unvalidated assumption that Postgres meets P-3 (`04-non-functional-requirements.md`:
"Graph traversal (impact analysis, depth ≤ 5), p95 < 1s") at realistic
volume.

## Scope

**In scope:** methodology, synthetic dataset shape, measured results, and
what the result does and does not license.

**Out of scope:** the real Delivery Graph kernel's design (Phase 1,
`docs/architecture/data/42-entity-model-and-ownership.md`); this benchmark
runs against a throwaway schema built only to exercise the traversal
mechanism.

---

## Method

Real Postgres 16, real RLS policies enabled (never disabled for the
benchmark — `docs/architecture/08-scalability-and-performance-engineering.md`
and `26-performance-strategy.md` both require benchmarking with policies
active, since RLS predicate evaluation is itself a named risk), queried
through the same two-role connection split used everywhere else in this
codebase: `platform_migrator` seeds the synthetic data, `platform_app` — the
actual runtime role, with neither `BYPASSRLS` nor table ownership — runs
every timed query.

**Schema** (`apps/api/database/migrations/2026_08_07_000002_create_graph_poc_tables.php`):
two tables, `graph_poc_versions` and `graph_poc_links`, carrying the exact
composite indexes `45-index-and-partitioning-strategy.md` specifies for the
real Delivery Graph kernel — forward `(tenant_id, from_version_id,
link_type)` and reverse `(tenant_id, to_version_id, link_type)`. RLS enabled
(not `FORCE`-d: the D-55 boundary under test is `platform_app`, which is
never the table owner and so is never exempt regardless of `FORCE` — see the
migration's comment for why forcing it would only have blocked the
legitimate administrative seeding).

**Traversal direction:** reverse ("what depends on this?") — the harder,
product-differentiating direction per `45`, requiring the index on the
target side of the edge.

**Command:** `php artisan graph:poc-benchmark --seed`
(`apps/api/app/Console/Commands/GraphPocBenchmark.php`). Re-runnable, not a
one-off script — the risk register's mitigation for TR-2 is "benchmark
early" with signal "traversal p95 approaching the P-3 budget," so this stays
in the tree to re-check that signal later, not just to produce this one
result.

## Synthetic Dataset

Chosen to stress two distinct things the docs flag as risks, not just to hit
a row count:

| Concern | How it's exercised |
| --- | --- |
| Does the `tenant_id`-leading index keep an unrelated tenant's data from being scanned? | 200 "noise" tenants × 250 versions each (50,000 rows, 50,000 links) sharing the same tables as the benchmarked tenant |
| Does traversal cost stay bounded when fan-out is high? (the specific "superlinear degradation with depth" risk in `26-performance-strategy.md`) | The benchmarked tenant: 10,000 versions as a DAG (~4 average fan-out) plus 20 deliberately injected "hub" nodes with 150 inbound links each — a foundational-artifact worst case |

**Totals seeded:** 60,000 versions, 88,996 links across 201 tenants.

## Result

200 random starting nodes in the benchmarked tenant, depth capped at 5
(matching P-3), each timed with `hrtime()` around the query alone (excludes
connection/session setup). Run five times (one fresh reseed, four against
the same seed) since starting-node selection is random and the seeded graph
has hub nodes — the range below is what actually varied, not a single
cherry-picked run:

| Metric | Range across 5 runs |
| --- | --- |
| Samples per run | 200 |
| p50 | 0.56 – 0.66 ms |
| p95 | 1.96 – 2.84 ms |
| p99 | 3.35 – 6.37 ms |
| max (single sample) | 3.78 – 109.09 ms |
| **P-3 budget** | **p95 < 1,000 ms** |
| **Result** | **PASS, every run — p95 never exceeded 0.3% of budget** |

**The single-sample max varies far more than p95 does, and why that's the
interesting finding.** The baseline query uses `UNION ALL` (not `UNION`), so
a traversal that reaches a hub node through more than one path counts that
node once per path rather than deduplicating — a real property of recursive
CTEs, not a bug in this benchmark. When a sampled start happened to sit
close to multiple hub nodes, "rows reached" ranged as high as 95,930 (more
path-instances than the benchmark tenant's 10,000 total versions) and that
single query took 109ms. Still two orders of magnitude inside the 1s budget,
but worth carrying into Phase 1's real design: **the real impact-analysis
query should return distinct impacted artifacts**, either via `UNION`
(dedup, slower) or a post-aggregation `DISTINCT` — matching what a user
actually wants from "what depends on this?" (a list of artifacts, not a
count of paths).

`EXPLAIN (ANALYZE, BUFFERS)` on sampled traversals confirms the reverse
composite index is used at every recursive step (`Index Scan using
graph_poc_links_reverse_idx`, RLS's tenant predicate folded directly into
the index condition) — not a sequential scan degrading with table size. This
is the detail that matters more than the raw timing: the margin holds
*because* the query plan is doing what the index design in `45` predicted,
not by accident of a small dataset.

## What This Licenses, and What It Doesn't

**Resolved:** TR-2 as originally scoped — "does the baseline (depth-bounded
recursive CTE over indexed `artifact_links`) meet P-3 at a dataset shaped
like a real large tenant, with RLS active and other tenants' data present in
the same tables?" Yes, with wide margin. The escalation path in `45`
(materialized closure tables, precomputed impact read models, a derived
graph store) is not needed at this volume and does not need to be built
speculatively — exactly what a Phase 0 spike is for.

**Not resolved, and not claimed:**

- **Production scale.** This is one large tenant's realistic subgraph, not
  the S-1 target of 50k tenants at Phase 4. The >300x margin (worst observed
  p95, 2.84ms, against the 1,000ms budget) is comfortable headroom, not a
  guarantee that never revisits — `19-risk-register.md`'s
  signal ("traversal p95 approaching the P-3 budget") is what re-triggers
  this benchmark, via the same re-runnable command, once real production
  volume and shape exist to measure.
- **Write-side cost.** Only traversal (read) is benchmarked. Link-write
  throughput under RLS is a separate, unmeasured concern.
- **Concurrent load.** These are sequential single-connection timings, not
  p95 under concurrent multi-tenant traffic competing for buffer cache and
  connections.

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-664 | P-3 baseline (depth-bounded recursive CTE, indexed per `45`) accepted without building the escalation path | Measured >300x inside budget on a realistically shaped, RLS-active dataset; building materialized closures or a derived graph store now would be speculative |
| D-665 | Graph traversal benchmark kept as a re-runnable Artisan command, not deleted after this result | TR-2's mitigation is continuous ("benchmark early," monitored signal), not a one-time gate |

## Dependencies

- **Depends on:** NFR targets (`04`), index and partitioning strategy (`45`),
  performance strategy (`26`), multi-tenancy strategy (`07`).
- **Depended on by:** roadmap Phase 0 exit criteria (`20`), risk register
  TR-2 (`19`).

## Future Improvements

- Re-run at real production volume once Phase 1's Delivery Graph kernel and
  actual tenant data exist, replacing this synthetic estimate with a
  measured one.
- Extend to concurrent multi-tenant load once a connection pooler is in the
  deployment path (the same gap noted in `apps/api/tests/Isolation/README.md`
  for the RLS proof of concept).
- Benchmark link-write throughput, not only traversal reads.
- Carry the `UNION ALL` vs. distinct-results finding above into Phase 1's
  real impact-analysis query design, and re-benchmark with `DISTINCT`
  included — the escalation-path table in `45` doesn't currently model this
  cost, which is smaller than a full re-architecture but not zero.
