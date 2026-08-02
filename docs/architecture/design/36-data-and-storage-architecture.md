# Data and Storage Architecture

## Purpose

Define where every class of data lives, how it is organized, partitioned,
indexed, searched, retained and deleted. Data outlives every application that
touches it (`23`), so these decisions carry the longest horizon in the
architecture.

## Scope

**In scope:** data classification, PostgreSQL design, table categories,
partitioning, indexing, blob strategy, vector storage, search architecture,
read replicas, retention and deletion.

**Out of scope:** technology selection rationale (`06`), tenant isolation
mechanics (`07`), caching (`37`).

---

## Data Classification

**Decision.** Every piece of data is classified into one of four tiers, and the
tier determines its store, durability guarantee and recovery obligation.

| Tier | Store | Durability | On loss |
| --- | --- | --- | --- |
| **System of record** | PostgreSQL | Full — PITR, replicated | Restore from backup; data loss is an incident |
| **Blob content** | Object storage | Full — versioned, replicated | Restore from versions |
| **Derived** | Search index, vector index, read models | None required | **Rebuild from source** |
| **Ephemeral** | Redis | None | Recompute or accept loss |

**Reasoning.** Without explicit classification, derived data accretes
authoritative status — someone stores something only in the search index, and now
a rebuild loses data. Classification makes "can this be rebuilt?" an answered
question rather than a hope discovered during an incident.

**The rule that makes it real: derived stores must be rebuildable from the system
of record at any time, and the rebuild must be exercised, not assumed.** A
rebuild path that has never been run is a rebuild path that does not work.

**Alternatives.** *Treat every store as authoritative* — maximum safety, and
turns every index into a backup and recovery obligation. *Classify informally* —
the default; drifts as soon as one engineer stores something convenient in the
wrong tier.

**Trade-offs.** Rebuild capability must be built and maintained even when never
used in anger. This is deliberate: `15` treats untested restore as an assumption
rather than a backup, and the same reasoning applies here.

---

## PostgreSQL Design

### Table categories

Five categories, each with distinct rules. Category determines RLS policy,
partitioning and retention.

| Category | `tenant_id` | RLS | Partitioned | Example |
| --- | --- | --- | --- | --- |
| **Tenant-scoped** | Required, non-null | **Yes** | By tenant range at Stage 4 | `requirements`, `artifacts`, `artifact_links` |
| **Global reference** | None | No | No | `plans`, `link_types`, `countries` |
| **Platform operational** | Nullable | No — operator-only access | By time | `feature_flags`, `system_jobs` |
| **Append-only audit** | Required | Yes (read), insert-only | By time (monthly) | `audit_log`, `access_log` |
| **Infrastructure** | Required where applicable | Yes | By time | `outbox`, `processed_events`, `idempotency_keys` |

**Every tenant-scoped table carries `tenant_id` as a non-nullable column with an
RLS policy, without exception.** A tenant-scoped table without a policy is a leak
waiting to happen, so its absence fails the build (`13`).

**Audit tables are insert-only at the privilege level**, not merely by
convention — the application role has `INSERT` and `SELECT`, never `UPDATE` or
`DELETE`. Tamper-evidence enforced by grants rather than discipline is the only
kind that survives a compromised application.

### Index strategy

**Decision.** Every index on a tenant-scoped table leads with `tenant_id`.

**Reasoning.** Every query on these tables filters by tenant — RLS guarantees it.
An index not leading with `tenant_id` cannot serve the tenant predicate
efficiently, so the planner either scans more than necessary or ignores the index.
Leading with it also gives natural clustering: one tenant's rows are physically
adjacent, so their working set is compact and cache-efficient.

This also aligns with the eventual sharding key (`08` Stage 4), which is a
deliberate convergence rather than a coincidence.

**Alternatives.** *Index by selectivity* — the textbook rule, and it is wrong
here because `tenant_id` is in the predicate of literally every query, and RLS
appends it whether the developer wrote it or not. *Partial indexes per tenant* —
unmanageable at 50k tenants.

**Trade-offs.** Slightly larger indexes; occasionally a query filtering only on a
highly selective non-tenant column is less optimal. Rare, and addressed with a
purpose-built index when measured.

### RLS policy performance

Policies are simple equality predicates against a session variable — deliberately,
because a policy containing a subquery or a join executes on **every row access,
on every query, forever**. Complex authorization belongs in the application's
policy layer (`31`); RLS enforces only the tenant boundary.

This is benchmarked in Phase 0 rather than assumed (`19` TR-1 risk table), since
RLS overhead landing on P-1 would be the isolation mechanism defeating the
performance budget.

---

## Partitioning

**Decision.** Time-based partitioning from the start on high-volume append-only
tables; tenant-range partitioning deferred until Stage 3–4 triggers fire.

| Table | Strategy | Rationale |
| --- | --- | --- |
| `audit_log` | Monthly by `occurred_at` | Retention enforced by dropping a partition rather than mass `DELETE` |
| `outbox` | Daily by `created_at` | Published rows pruned by dropping partitions |
| `processed_events` | Daily | TTL by partition drop |
| `artifact_versions` | Tenant range (Stage 3) | Highest-volume tenant-scoped table |
| `artifact_links` | Tenant range (Stage 3) | 10M edges per tenant target (S-7) |

**Why time partitioning is worth doing early despite P10.** Retention on a large
unpartitioned table means deleting millions of rows — a long-running transaction
that bloats the table, competes with production traffic and requires vacuum
afterwards. Dropping a partition is near-instant and produces no bloat. The cost
of partitioning at creation is negligible; the cost of retrofitting it onto a
100M-row table is a migration project.

This is a P10 judgment: partitioning `audit_log` is cheap now and expensive
later, so it is not premature. Tenant-range partitioning *is* premature and is
deferred with its trigger recorded.

---

## Blob Strategy

**Decision.** Artifact content above a size threshold, all uploads and all
exports live in object storage; PostgreSQL holds metadata and a content
reference.

| Data | Store | Reason |
| --- | --- | --- |
| Artifact content ≤ 64 KB | PostgreSQL (JSONB) | Transactional with its version; queryable |
| Artifact content > 64 KB | Object storage + reference | Keeps rows and backups small |
| Uploaded documents | Object storage | Arbitrary size, untrusted, never queried directly |
| Generated exports | Object storage, lifecycle-expired | Regenerable; no long-term value |
| Embeddings | PostgreSQL (pgvector) | Must participate in tenant-scoped queries |

**Reasoning for the threshold rather than "always blobs".** Small artifact
content in JSONB is transactionally consistent with its version row, queryable,
and included in PITR — all valuable. Large content in the database bloats
backups, slows restores (A-7) and consumes shared buffer cache that should hold
hot rows. The threshold captures the benefit of each.

**The consistency cost, stated honestly:** a blob write and its metadata row are
not atomic. Handled by writing the blob first and committing the reference
second, so a failure leaves an orphaned blob rather than a dangling reference —
an orphan is reclaimable by a sweeper, a dangling reference is a broken artifact.
**Choosing which way the inconsistency falls is the design decision**, and it
falls toward garbage rather than corruption.

**Access control:** blobs are never public. Reads go through short-lived signed
URLs issued after an authorization check (D-315), and object keys are
tenant-prefixed so a leaked key cannot be manipulated to traverse tenants.

---

## Vector Storage

**Decision.** Embeddings in pgvector, in the same database, subject to the same
RLS policies.

**Reasoning.** Tenant-scoped retrieval (T-7) is the requirement, and keeping
vectors in PostgreSQL makes tenant filtering a database-level guarantee rather
than an application-level one. A separate vector store means the isolation
guarantee must be reimplemented and independently verified in a second system —
duplicating the highest-consequence control in the platform.

### Embedding lifecycle — the part usually missed

**Embeddings are model-specific.** Vectors produced by one embedding model are
not comparable with those from another, so changing the embedding model
invalidates the entire index. This has three consequences that must be designed
for rather than discovered:

1. **The embedding model identifier is stored with every vector.** Without it,
   there is no way to know which vectors are stale after a model change.
2. **Re-embedding is a planned migration**, not a side effect of a deploy: new
   model → embed in parallel into a new column or table → verify retrieval
   quality → switch → drop old. The same expand-contract discipline as schema
   changes (D-120).
3. **Mixed-model indexes must be impossible.** Queries filter on the model
   identifier so a partially migrated index cannot silently return incomparable
   results — which would degrade retrieval quality invisibly.

**Migration trigger to a dedicated store** (D-45): retrieval latency breaching
P-6's budget, or vector operations measurably competing with transactional load.
Retrieval sits behind an interface so the change touches one adapter.

---

## Search Architecture

**Decision.** PostgreSQL full-text search initially; OpenSearch only when a
measured requirement demands it.

| Aspect | Phase 1–2 (Postgres FTS) | Trigger to migrate |
| --- | --- | --- |
| Index | GIN on `tsvector`, `tenant_id`-leading composite | — |
| Tenant scope | RLS — same guarantee as all data | — |
| Ranking | `ts_rank` | Relevance tuning demonstrably insufficient |
| Facets | SQL aggregates | Faceting becomes a primary interaction |
| Volume | Adequate to S-6 targets | Index size or latency breaches P-8 |

**Reasoning.** Postgres FTS satisfies P-8 at early volumes with zero additional
infrastructure, and — critically — inherits tenant isolation from RLS for free.
Adding a search cluster in Phase 1 would duplicate the isolation problem into a
second system while adding an operational component, for relevance quality
nobody has yet asked for.

**Alternatives.** *OpenSearch from day one* — better relevance and faceting;
rejected as speculative generality (P10) with a real isolation cost. *Third-party
search SaaS* — fast to adopt, and ships tenant content to another processor for
a capability we can meet in-house.

**When migration comes**, the isolation requirement transfers with it: a
tenant-filtered query at the search layer, verified by the isolation suite
(T-1), with the filter applied before ranking exactly as with vectors (D-23).

---

## Read Replicas and Consistency

| Read type | Target | Reason |
| --- | --- | --- |
| Read-after-write within a user's action | **Primary** | Must observe own writes |
| Interactive reads in an editing session | Primary | Same |
| List and browse views | Replica | Small lag acceptable |
| Analytics, reporting | Replica | Lag irrelevant |
| Search and traversal | Replica | Lag acceptable |
| Export generation | Replica | Point-in-time snapshot is fine |

**Replica routing is opt-in per read path, never a global default** (D-70).
Making replicas the default and marking exceptions inverts the risk: a missed
annotation becomes a correctness bug that appears only under lag, which is
exactly the bug class that is hardest to reproduce.

---

## Retention and Deletion

| Data | Retention | Enforcement |
| --- | --- | --- |
| Artifacts and versions | Life of tenant + 30 days | Tenant deletion process |
| Audit log | Minimum 1 year (SEC-5) | Partition drop |
| Integration events | 24 months (D-381) | Partition drop |
| Domain events | 90 days | Partition drop |
| Job records | 90 days | Partition drop |
| Idempotency keys | 24 hours | Partition drop |
| Exports | 30 days | Object lifecycle policy |
| Telemetry | Tiered — traces 14 days, metrics 13 months | Backend policy |
| Backups | 35 days PITR (C-6) | Managed |

### Tenant deletion (T-5)

**The completeness problem.** Tenant data spreads to every derived store, and a
cascade delete on the primary tables reaches none of them. Each requires an
explicit deletion path:

| Store | Deletion mechanism |
| --- | --- |
| PostgreSQL tenant-scoped tables | Cascading delete by `tenant_id` |
| Object storage | Prefix deletion — tenant-prefixed keys make this tractable |
| Vector index | Deleted with rows (same database) |
| Search index | Explicit tenant-scoped deletion |
| Redis (cache, sessions, rate limits) | Key-pattern deletion by tenant prefix |
| Read models and projections | Tenant-scoped deletion |
| Telemetry | Tenant-scoped deletion or documented retention expiry |
| Backups | Expire per retention policy — **cannot be selectively purged** |

**Backups are the honest exception.** Selectively removing one tenant from
point-in-time backups is not feasible without destroying the backup's integrity.
The commitment is therefore: active data deleted within 30 days, backup copies
expiring within the 35-day retention window, documented plainly to customers
rather than obscured.

**A deletion audit verifies each store rather than trusting a single cascade** —
the verification is the requirement, not the deletion command.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-382 | Four data tiers; derived stores must be rebuildable and the rebuild exercised | Otherwise derived data silently becomes authoritative and a rebuild loses it |
| D-383 | Five table categories determining RLS, partitioning and retention | Makes the rules mechanical rather than per-table judgment |
| D-384 | Audit tables are insert-only at the privilege level | Tamper-evidence by grants survives a compromised application; convention does not |
| D-385 | Every tenant-scoped index leads with `tenant_id` | Every query filters by it; also gives clustering and aligns with the future shard key |
| D-386 | RLS policies are simple equality predicates only | A policy with a subquery executes per row, on every query, forever |
| D-387 | Time-partitioning from creation on append-only high-volume tables | Retention by partition drop; retrofitting onto 100M rows is a migration project |
| D-388 | Artifact content over 64 KB in object storage, below it in JSONB | Captures transactional consistency for small content and small backups for large |
| D-389 | Blob written before its reference commits | Forces inconsistency toward reclaimable orphans rather than broken artifacts |
| D-390 | Object keys are tenant-prefixed | A leaked key cannot be manipulated to traverse tenants |
| D-391 | Embeddings stored in PostgreSQL under the same RLS | Avoids reimplementing the highest-consequence control in a second system |
| D-392 | Embedding model identifier stored with every vector | Without it there is no way to identify stale vectors after a model change |
| D-393 | Re-embedding is a planned expand-contract migration | A model change silently invalidates the entire index |
| D-394 | Queries filter on embedding model so mixed-model results are impossible | Partial migration would degrade retrieval quality invisibly |
| D-395 | Postgres FTS first; search isolation transfers with any migration | FTS inherits RLS isolation free; a search cluster duplicates the isolation problem |
| D-396 | Replica reads opt-in per path; primary is the default | A missed annotation must not become a lag-dependent correctness bug |
| D-397 | Tenant deletion has an explicit path per store, verified by audit | A cascade reaches no derived store |
| D-398 | Backup non-purgeability documented plainly to customers | Selective backup purge is infeasible; obscuring it is worse than stating it |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| RLS overhead breaches P-1 on hot queries | Isolation mechanism defeats the performance budget | Benchmarked in Phase 0 with policies enabled; simple predicates only |
| Derived store rebuild has never been run and does not work | Unrecoverable loss of a "rebuildable" store | Rebuild exercised on a schedule, like restore drills (D-161) |
| Orphaned blobs accumulate | Storage cost | Sweeper reconciles blobs against references; orphans are the deliberate failure direction |
| Embedding model changed without planned re-embedding | Retrieval quality degrades invisibly | Model identifier on every vector; queries filter on it |
| Tenant deletion misses a derived store | Compliance violation (T-5) | Per-store deletion path with an audit that verifies each |
| Partition maintenance not automated | Writes fail when no partition exists for the current period | Partition creation automated and monitored ahead of need |
| Graph edge volume outgrows unpartitioned tables | Traversal latency breaches P-3 | Tenant-range partitioning trigger monitored (`08` Stage 3) |

## Dependencies

- **Depends on:** technology (`06`), tenancy (`07`), scalability (`08`), domain
  model (`32`).
- **Depended on by:** caching and queueing (`37`), AI integration (`40`),
  resilience (`41`).

## Future Improvements

- Benchmark RLS overhead and graph traversal in Phase 0 against P-1 and P-3.
- Automate partition lifecycle before the first partitioned table carries
  production volume.
- Add a scheduled derived-store rebuild exercise alongside restore drills.
- Publish the tenant deletion verification checklist as an auditable artifact.
