# Index and Partitioning Strategy

## Purpose

Specify how data is indexed and partitioned to meet the latency budgets in `26`
and the volume targets in `04`, and how both stay correct as data grows.
Indexing and partitioning are the two decisions that determine whether a query
that is fast at 10,000 rows is still fast at 100 million.

## Scope

**In scope:** index design principles, index types and where each applies, the
access-pattern-to-index map, index budget and maintenance, partitioning strategy
per table class, and the partition lifecycle.

**Out of scope:** query-level optimization (`26`), storage tiering (`46`).

---

## Index Design Principles

### P1 · Every index serves a known access pattern

**Decision.** An index is created only for a specific, documented query pattern.
Speculative indexes are rejected in review.

**Reasoning.** Every index has a permanent write cost: each insert, update and
delete maintains it, and each consumes storage and cache. An index serving no
query is pure cost — and unused indexes accumulate silently because nothing fails
when they are useless.

The reverse failure is equally real: the missing index that turns a fast query
into a sequential scan somewhere between staging and production volume.
Access-pattern-driven design addresses both by making the question "which query
does this serve?" answerable for every index and "which index serves this?"
answerable for every query.

**Alternatives.** *Index defensively* — index anything that might be filtered;
produces write amplification and cache pressure for queries nobody runs.
*Index reactively* — add indexes only when something is slow; means discovering
the need in production, and the discovery is a customer-visible incident.

**Trade-offs.** Requires knowing access patterns at design time, which is
imperfect — some will be wrong, and unused indexes must be found and removed.

**Benefits.** Write cost proportional to actual read benefit. Index review is
possible because each index has a stated justification.

**Long-term impact.** Index sets grow monotonically without governance. A
ten-year-old table with forty indexes, most unused, is common and is a real drag
on every write.

### P2 · Tenant-scoped indexes lead with `tenant_id`

Restated from `36` (D-385) because it is the single most consequential indexing
rule here: every query on a tenant-scoped table filters by tenant — RLS appends
it whether or not the developer wrote it. An index not leading with `tenant_id`
cannot serve that predicate, and the planner falls back to scanning.

It also produces natural clustering, so one tenant's working set is physically
compact, and it aligns with the eventual shard key (`08`).

### P3 · Index the predicate, cover the projection

Composite indexes are ordered: equality columns first, then range columns, then
sort columns. Columns needed only for output are added with `INCLUDE` rather than
as key columns — they make the index covering without enlarging its search
structure.

### P4 · Partial indexes for skewed predicates

Where a query always filters on a small subset — active records, pending
approvals, undeleted rows — a partial index over that subset is dramatically
smaller and stays cache-resident. This pairs directly with soft delete (`46`):
`WHERE deleted_at IS NULL` in the index definition means deleted rows cost
nothing in the index at all.

---

## Index Types and Their Use

| Type | Use | Where |
| --- | --- | --- |
| **B-tree** | Equality, range, sort | Default for nearly everything |
| **GIN** | JSONB containment, full-text search | `artifact_versions.content`, `tsvector` columns |
| **GiST** | Range overlap, exclusion constraints | Sprint date ranges, capacity windows |
| **BRIN** | Very large, naturally-ordered append-only tables | `audit_log`, `outbox`, telemetry tables |
| **HNSW** (pgvector) | Approximate nearest neighbour | `embeddings.vector` |
| **Hash** | Equality only, large keys | Rarely — B-tree usually suffices |
| **Unique partial** | Uniqueness among live rows | Soft-deleted tables (`46`) |

**BRIN deserves specific mention.** For an append-only table whose physical order
correlates with a timestamp — audit entries, outbox rows — a BRIN index is orders
of magnitude smaller than a B-tree while serving range scans well. On a
100-million-row audit table, the difference between a multi-gigabyte B-tree and a
few-megabyte BRIN is the difference between an index that fits in cache and one
that competes with the working set.

**HNSW over IVFFlat for vectors:** better recall at a given latency and no
training step, at the cost of slower build and more memory. Given retrieval
quality directly determines AI output quality (`40`), recall is the right thing
to optimize.

---

## Access Pattern Map

Principal patterns and the index serving each. Illustrative rather than
exhaustive — each context adds its own during design.

| Access pattern | Index |
| --- | --- |
| List a project's artifacts by type and status | `(tenant_id, project_id, type, status)` INCLUDE (title, updated_at) |
| Fetch an artifact's current version | `(tenant_id, artifact_id, version_number DESC)` |
| Traverse links outward from a version | `(tenant_id, from_version_id, link_type)` |
| Traverse links inward (reverse impact) | `(tenant_id, to_version_id, link_type)` |
| Pending approvals for an actor | Partial: `(tenant_id, assigned_to)` WHERE status = 'pending' |
| Requirements not yet covered by a task | Partial on the traceability projection |
| Full-text search within a tenant | GIN on `tsvector`, plus `tenant_id` as a leading B-tree filter |
| Semantic retrieval within a tenant | HNSW on vector, **with `tenant_id` filtered first** (D-23) |
| Audit entries for a tenant in a time range | BRIN on `occurred_at` + `(tenant_id, occurred_at)` |
| Unpublished outbox rows | Partial: `(created_at)` WHERE published_at IS NULL |
| Jobs by status and queue age | Partial: `(queue, created_at)` WHERE status IN ('pending','processing') |
| Per-tenant AI cost in a period | `(tenant_id, occurred_at)` INCLUDE (cost, tokens) |

**Two rows are load-bearing for correctness rather than speed.**

The **reverse link traversal** index is what makes impact analysis possible in
both directions — "what depends on this?" is the question the product exists to
answer, and it requires an index on the *target* side of the edge, which is easy
to omit when only forward traversal is considered.

The **vector retrieval** row is a reminder that the tenant filter is applied
before the ANN search, not after (D-324, D-23). An HNSW index searched globally
and filtered afterwards both leaks and returns too few in-tenant results.

---

## Index Budget and Maintenance

| Concern | Rule |
| --- | --- |
| **Budget** | A table with more than ~6 indexes requires justification in review |
| **Write cost** | Measured on high-write tables before adding an index |
| **Unused detection** | Index usage statistics reviewed quarterly; unused indexes removed (P13) |
| **Redundancy** | An index whose columns are a prefix of another is redundant and removed |
| **Bloat** | Monitored; rebuilt concurrently when bloat exceeds threshold |
| **Creation** | Always concurrently in production — a blocking index build on a large table is an outage |
| **Verification** | Query plans reviewed for new query patterns as a merge gate (`16`) |

**The redundancy rule catches a common accumulation:** `(tenant_id, project_id)`
is entirely served by `(tenant_id, project_id, status)`. Both existing means
double write cost for one capability.

---

## Partitioning Strategy

**Decision.** Time-based partitioning from table creation on append-only
high-volume tables. Tenant-range partitioning deferred until its trigger fires.

**Reasoning.** Restated and deepened from `36` (D-387): retention on a large
unpartitioned table means deleting millions of rows — a long transaction that
bloats the table, competes with production traffic, and requires vacuum
afterwards. Dropping a partition is near-instant and produces no bloat.

Partitioning at creation costs almost nothing; retrofitting it onto a
100-million-row table is a migration project requiring a full table rewrite. This
is a P10 judgment — cheap now, expensive later, therefore not premature.

Tenant-range partitioning is the opposite: it complicates every query plan and
buys nothing until volume demands it, so it waits for its trigger.

**Alternatives.** *No partitioning* — simplest, and makes retention an
operational problem that gets deferred until the table is too large to fix.
*Partition everything* — uniform, and adds planning overhead and maintenance to
small tables for no benefit. *Tenant partitioning from day one* — aligns with the
future shard key and is unjustifiable complexity at Phase 1 volumes.

**Trade-offs.** Partitioned tables need automated partition creation ahead of
need — a missing partition means write failures, which is a self-inflicted
outage. Query plans across many partitions can be slower if the partition key is
absent from the predicate.

**Benefits.** Retention becomes a metadata operation. Query pruning on the
partition key. Old partitions can be moved to cheaper storage (`46`).

### Partition plan

| Table | Key | Interval | Retention | Stage |
| --- | --- | --- | --- | --- |
| `audit_log` | `occurred_at` | Monthly | 12+ months | From creation |
| `outbox` | `created_at` | Daily | 7 days after publish | From creation |
| `processed_events` | `processed_at` | Daily | 7 days | From creation |
| `idempotency_keys` | `created_at` | Daily | 24 hours | From creation |
| `generation_records` | `occurred_at` | Monthly | 24 months | From creation |
| `job_records` | `created_at` | Monthly | 90 days | From creation |
| `integration_events` | `occurred_at` | Monthly | 24 months | From creation |
| `artifact_versions` | `tenant_id` range | — | Life of tenant | **Stage 3 trigger** |
| `artifact_links` | `tenant_id` range | — | Life of tenant | **Stage 3 trigger** |

**Stage 3 trigger** (`08`): largest tables exceeding ~100M rows, or per-tenant
query latency degrading measurably with total table size.

### Partition lifecycle

```
   T-2 months   partition pre-created (automated, monitored)
        │
   T            active — receives writes
        │
   T+1 month    read-only; still hot storage
        │
   T+3 months   moved to warm storage (`46`)
        │
   retention    exported to cold archive, then partition dropped
```

**Pre-creation is automated and alerted**, because the failure mode of a missing
partition is that writes fail — a complete outage for that table, caused by a
maintenance task nobody was watching. The alert fires on *absence of future
partitions*, not on the failure itself.

---

## Graph Traversal Indexing

The performance-critical path (P-3), and the one whose assumption is unvalidated
(TR-2).

**Baseline:** recursive CTE over `artifact_links`, served by the forward and
reverse composite indexes above, depth-bounded.

**Escalation path**, pre-considered so the decision is not invented under
pressure:

| If | Then |
| --- | --- |
| Depth-bounded traversal meets P-3 | Baseline stands |
| Deep traversal is slow but shallow is fine | Materialize a closure table for common depths |
| Traversal is slow at all depths | Precomputed impact summary read model (`43`), refreshed on link events |
| Graph queries dominate load | Derived graph read model in a dedicated store — **never a second system of record** (`32`) |

**The last row's constraint matters:** a graph database, if ever adopted, is a
*derived read model* rebuilt from PostgreSQL, never an authoritative store. Two
systems of record means distributed transactions and synchronization bugs to
optimize an operation we can already meet.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-490 | Every index serves a documented access pattern; speculative indexes rejected | Unused indexes are permanent write cost that nothing surfaces as a failure |
| D-491 | Composite indexes order equality, then range, then sort; output columns via `INCLUDE` | Covering without enlarging the search structure |
| D-492 | Partial indexes for skewed predicates, especially `deleted_at IS NULL` | Deleted rows then cost nothing in the index |
| D-493 | BRIN on very large append-only time-ordered tables | Orders of magnitude smaller than B-tree; fits in cache where B-tree competes with the working set |
| D-494 | HNSW over IVFFlat for vectors | Recall determines AI output quality; worth the build cost |
| D-495 | Reverse-direction link index is mandatory, not optional | "What depends on this?" is the product's core question and needs the target-side index |
| D-496 | Index budget of ~6 per table before justification; redundant prefixes removed | Index sets grow monotonically without governance |
| D-497 | Indexes always created concurrently in production | A blocking build on a large table is a self-inflicted outage |
| D-498 | Time partitioning from creation on append-only high-volume tables | Retention becomes a metadata operation; retrofitting requires a full rewrite |
| D-499 | Tenant-range partitioning deferred to a measured trigger | Complicates every plan and buys nothing until volume demands it |
| D-500 | Partition pre-creation automated, with alerting on absence of future partitions | A missing partition fails writes — an outage caused by unwatched maintenance |
| D-501 | Any future graph store is a derived read model, never a system of record | Two systems of record means distributed transactions and sync bugs |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Index set grows unmanaged | Write amplification; cache pressure | Budget rule; quarterly usage review with removal |
| Missing index discovered in production | Latency incident | Query plan review as a merge gate; load tests at realistic volume |
| Partition automation fails silently | Write failures on a table | Alert on absence of future partitions, not on the failure |
| RLS predicate prevents index use | Isolation mechanism defeats performance | Simple equality predicates only (D-386); benchmarked in Phase 0 |
| Vector index built globally then filtered | Leak plus poor in-tenant recall | Tenant filter before ANN search, asserted by the isolation suite |
| Graph traversal fails P-3 at real volume (TR-2) | The differentiating feature feels broken | Phase 0 benchmark; four-step escalation path pre-considered |
| Tenant-range partitioning needed sooner than expected | Migration under pressure | Trigger monitored continuously; design prepared before it fires |

## Dependencies

- **Depends on:** data and storage (`36`), entity model (`42`), read/write models
  (`43`), performance strategy (`26`).
- **Depended on by:** retention and archiving (`46`), backup and recovery (`49`).

## Future Improvements

- Publish the full index catalogue per table with its justifying access pattern,
  generated from migrations so it cannot drift.
- Add automated unused-index reporting to the quarterly review.
- Benchmark BRIN versus B-tree on the audit table at realistic volume rather
  than relying on the general result.
