# Write Models and Read Models

## Purpose

Define how data is shaped for writing versus for reading, when the two diverge,
and how derived read models are built, kept current and rebuilt. This is the
platform's position on CQRS: adopted selectively, where the access patterns
genuinely differ, and rejected as a blanket architecture.

## Scope

**In scope:** the write path, the read path, criteria for introducing a read
model, the read model catalogue, projection mechanics, consistency budgets and
rebuild strategy.

**Out of scope:** entity ownership (`42`), index design (`45`), event delivery
(`35`).

---

## Position on CQRS

**Decision.** The same normalized model serves reads and writes by default.
Separate read models are introduced **per access pattern**, only where a
measured or structurally obvious mismatch exists — never as a global
architectural style.

**Reasoning.** Full CQRS — every write through a command model, every read from a
projection — doubles the number of models, makes every read eventually
consistent, and requires a projection for access patterns that a simple query
would have served perfectly. It is a powerful pattern for a small number of
genuinely divergent access patterns and a heavy tax when applied uniformly.

Most of our reads are "fetch this aggregate" or "list these entities filtered by
tenant and project" — exactly what the normalized write model serves best, with
strong consistency and no projection lag. Introducing a projection for those
would add latency, staleness and rebuild machinery in exchange for nothing.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| **Full CQRS everywhere** | Uniform, and every read becomes eventually consistent, every model is written twice, and the team pays projection cost on queries that never needed it |
| **No read models at all** | Simplest; cross-context reporting then requires either cross-boundary `JOIN`s (forbidden, D-460) or N+1 service calls, both unacceptable |
| **Read models only for analytics** | The obvious middle; too narrow — traceability and impact views have the same mismatch and are user-facing |
| **Selective, per access pattern** *(chosen)* | Complexity paid only where it buys something; requires judgment, which is stated below as criteria |

**Trade-offs.** Judgment is required per case, and judgment is applied
inconsistently without criteria — hence the explicit test below. Two ways of
reading data also means engineers must know which applies where.

**Benefits.** Strong consistency preserved for the majority of reads. Projection
machinery exists only where it earns its cost. Analytics gets what it needs
without any context exposing its tables.

**Long-term impact.** Blanket CQRS is difficult to unwind once adopted, because
every consumer depends on the projections. Selective adoption keeps the option
open in both directions.

### When a read model is justified

A read model is introduced when **at least two** hold:

1. The read spans **multiple bounded contexts** (a `JOIN` would violate D-460).
2. The read shape is **fundamentally different** from the write shape —
   aggregated, denormalized, or pivoted.
3. The query is **too expensive** against the normalized model to meet its
   latency budget (`26`), demonstrated by measurement.
4. The read volume is **far higher** than write volume for the same data.
5. **Staleness is acceptable** to the consumer, stated explicitly.

**Point 5 is a veto.** If the consumer requires read-after-write consistency, a
projection is the wrong answer regardless of the other criteria — the correct
answer is a better query or a better index.

---

## The Write Path

```
   Command (from HTTP handler or job)
        │
   ┌────▼─────────────────────────────────────────┐
   │ Application layer                             │
   │  · begin transaction                          │
   │  · load aggregate via repository              │
   │  · check optimistic version                   │
   └────┬─────────────────────────────────────────┘
   ┌────▼─────────────────────────────────────────┐
   │ Domain                                        │
   │  · aggregate enforces its invariants (`32`)   │
   │  · raises domain events                       │
   └────┬─────────────────────────────────────────┘
   ┌────▼─────────────────────────────────────────┐
   │ Infrastructure — ONE TRANSACTION              │
   │  · persist aggregate (normalized tables)      │
   │  · increment version                          │
   │  · append audit entry (`47`)                  │
   │  · write integration events to outbox (`35`)  │
   └────┬─────────────────────────────────────────┘
        │ commit
        ▼
   Outbox relay → events → projections, search, embeddings
```

**Write model properties:**

| Property | Value |
| --- | --- |
| Shape | Normalized, third normal form by default |
| Consistency | Strong within the aggregate (D-330) |
| Concurrency | Optimistic — version column, conflicting write fails (D-340) |
| Tenant scope | `tenant_id` non-null, RLS-enforced (`07`) |
| Transaction scope | Exactly one aggregate (D-330) |
| Side effects | Only via outbox — never a direct publish (D-371) |

**Denormalization on the write side is permitted only for invariant
enforcement.** Storing an `Estimate.total` alongside its line items is
acceptable, because the aggregate enforces total = sum(items) transactionally.
Storing a `created_by_name` because it is convenient for display is not — that is
a read concern, it duplicates data the kernel owns, and it makes GDPR erasure
substantially harder (`48`).

---

## The Read Path

```
   Query
     │
     ├─ Aggregate fetch ──────────▶ write tables, primary
     │  (edit, detail view)           strong consistency
     │
     ├─ Simple list/filter ───────▶ write tables, replica
     │  (browse, search within)      lag acceptable
     │
     ├─ Cross-context view ───────▶ READ MODEL
     │  (portfolio, traceability)    eventually consistent
     │
     ├─ Full-text search ─────────▶ search index (`36`)
     │
     ├─ Semantic retrieval ───────▶ vector index (`36`)
     │
     └─ Graph traversal ──────────▶ write tables + traversal engine
        (impact analysis)             optionally materialized (`45`)
```

**Replica routing is opt-in per path** (D-396). Read-after-write paths — the read
immediately following an approval, the editing session — go to the primary.

---

## Read Model Catalogue

Each is a projection built from integration events, owned by its consumer,
rebuildable from scratch.

| Read model | Owner | Sources | Serves | Lag budget |
| --- | --- | --- | --- | --- |
| **Traceability Matrix** | Analytics | Requirements, Design, Planning, Quality events | "Which tasks and tests cover this requirement?" (G3) | 30 s |
| **Portfolio Health** | Analytics | Planning, Execution, Estimation, Commercial | Founder dashboard (P1) | 5 min |
| **Estimate vs Actual** | Analytics | Estimation, Execution | G2 calibration and reporting | 15 min |
| **Project Activity Feed** | Analytics | All contexts | "What happened on this project?" | 10 s |
| **Artifact Index** | Delivery Graph | Graph events | Fast artifact listing with current status | 5 s |
| **Impact Summary** | Delivery Graph | Graph link events | Precomputed impact counts for hot artifacts | 60 s |
| **AI Usage and Cost** | Billing | `GenerationRecord` aggregation | Per-tenant cost (O-5), budget enforcement | 60 s |
| **Search Index** | Platform | Artifact version events | Full-text search (`36`) | 60 s |
| **Vector Index** | AI Orchestration | Approved version events | Semantic retrieval (`40`) | 5 min |
| **Stakeholder View** | Commercial | Curated Commercial + Requirements events | External stakeholder portal (P8) | 60 s |

**`Stakeholder View` is a security-motivated read model, not a performance one.**
Building it as a projection containing only explicitly shared content means the
external portal queries a model that *physically cannot* contain unshared data —
far stronger than filtering the full model at query time, where a filter bug is
a disclosure.

**`AI Usage and Cost` is a projection but is reconciled against `GenerationRecord`
monthly** (D-445), because it feeds billing and a drifted projection would
mis-bill.

---

## Projection Mechanics

```
   Integration event ──▶ Projection consumer
                              │
                         dedupe by eventId (D-374)
                              │
                         apply to read model
                              │
                         record checkpoint (position)
```

| Property | Requirement |
| --- | --- |
| **Idempotent apply** | Redelivery must not double-count. Aggregations use upserts keyed on source identity, never blind increments. |
| **Checkpointed** | Position recorded so a restart resumes rather than replaying from zero |
| **Rebuildable** | From event history alone, with no manual steps (D-382) |
| **Versioned** | The projection's shape carries a version |
| **Tenant-scoped** | `tenant_id` on every row, RLS-enforced — a projection is not exempt from isolation |
| **Lag-monitored** | Lag measured and alerted against its stated budget |

**Blind increments are the classic projection bug.** `count = count + 1` on
redelivery produces a silently wrong number that no test catches and no user
questions. Upserting a row keyed on the source event's identity and deriving the
count makes redelivery harmless.

### Projection rebuild — blue-green

**Decision.** A projection whose shape changes is **rebuilt into a new version
alongside the old**, then switched — never migrated in place.

**Reasoning.** In-place migration of a projection means writing a migration for
derived data that could simply be recomputed, and it leaves the projection
inconsistent during the migration. Since the projection is by definition
reproducible from events, rebuilding is both simpler and provably correct.

```
   v2 projection created empty
        │
   replay events from retention window into v2
        │
   v2 catches up to live (tail follows both v1 and v2)
        │
   verify v2 against v1 on overlapping queries
        │
   switch readers to v2  ──▶  drop v1
```

**Trade-offs.** Storage for two projections during the rebuild, and rebuild time
proportional to event history. Bounded by the 24-month integration event
retention (D-381) — which is one reason that retention exists.

**Benefits.** Zero-downtime shape changes. Verification against the old version
before switching. Trivial rollback — switch back.

---

## Consistency Budget

Staleness is a contract with the consumer, stated rather than discovered.

| Consumer | Tolerance | If exceeded |
| --- | --- | --- |
| Editing an artifact | Zero — primary read | N/A |
| Approval flows | Zero — primary read | N/A |
| Artifact listing | 5 s | Alert |
| Search results | 60 s | Alert |
| Traceability matrix | 30 s | Alert |
| Dashboards | 5 min | Alert |
| Cost and budget enforcement | 60 s | **Alert and fail closed** — budget checks fall back to the authoritative record |
| Analytics reporting | 15 min | Ticket |

**Where staleness is user-visible, the UI says so** (D-341) — "updated 2 minutes
ago" rather than presenting a stale figure as current.

**Cost enforcement fails closed to the authoritative source** when the projection
is stale: a budget check against a lagging projection could permit spend beyond a
cap, so it reads `GenerationRecord` directly rather than trusting a stale
aggregate.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-470 | CQRS adopted selectively per access pattern, not as a global style | Blanket CQRS doubles models and makes every read eventually consistent for no gain |
| D-471 | A read model requires at least two of five stated criteria | Judgment without criteria is applied inconsistently |
| D-472 | Required read-after-write consistency vetoes a read model | The correct answer is a better query or index, not a projection |
| D-473 | Write-side denormalization only for invariant enforcement | Display convenience duplicates kernel-owned data and complicates GDPR erasure |
| D-474 | Projections use upserts keyed on source identity, never blind increments | Redelivery otherwise produces silently wrong aggregates |
| D-475 | Projections are checkpointed, versioned, tenant-scoped and lag-monitored | A projection is not exempt from isolation or from observability |
| D-476 | Projection shape changes are blue-green rebuilds, never in-place migrations | Derived data should be recomputed, not migrated; verification before switch is free |
| D-477 | `Stakeholder View` is a projection for security, not performance | A model that cannot contain unshared data beats filtering one that can |
| D-478 | Every consumer has a stated staleness budget, monitored and alerted | Staleness discovered by users is a defect; staleness agreed in advance is a design |
| D-479 | Budget enforcement falls back to the authoritative record when its projection is stale | A stale aggregate could permit spend beyond a cap |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Read models proliferate until every query has one | The blanket CQRS cost we avoided, arrived at incrementally | Criteria required and reviewed; new projections justified in an ADR when cross-context |
| Projection lag exceeds budget under load | Users act on stale data | Lag monitored and alerted; UI surfaces staleness |
| Projection rebuild exceeds the event retention window | Cannot rebuild fully | 24-month integration event retention sized for this; snapshot base state where needed |
| Non-idempotent projection logic ships | Silently wrong aggregates | Redelivery exercised in tests; upsert pattern enforced in review |
| Read model diverges from source without detection | Wrong reporting; wrong billing | Reconciliation checks (`41`); cost projection reconciled monthly |
| Projections skip RLS because they are "derived" | Cross-tenant exposure through a read model | Tenant scoping required; isolation suite covers projections |

## Dependencies

- **Depends on:** domain model (`32`), events (`35`), entity model (`42`).
- **Depended on by:** data flow (`44`), indexes (`45`), GDPR (`48`).

## Future Improvements

- Publish each projection's event dependencies explicitly, so a producer change
  can identify affected projections automatically.
- Add automatic reconciliation of every projection against its source on a
  schedule, not only the cost projection.
- Measure whether the traceability matrix genuinely needs a projection once real
  query patterns exist — it may be servable from the graph directly.
