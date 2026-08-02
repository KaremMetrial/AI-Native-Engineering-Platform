# Resilience and Failure Recovery

## Purpose

Define how the platform behaves when things fail — which they will. This
document catalogues the failure modes, specifies the patterns that contain them,
and states explicitly what still works when each dependency is unavailable.

The degradation matrix is the central artifact: a system whose partial-failure
behaviour is undefined has that behaviour decided by accident, during an
incident, by whoever is on call.

## Scope

**In scope:** failure taxonomy, resilience patterns and where each applies, the
degradation matrix, data recovery, reconciliation, and failure injection.

**Out of scope:** disaster recovery infrastructure (`15`), incident process
(`15`), timeout and retry budgets (`34`).

---

## Failure Taxonomy

**Decision.** Failures are classified by *shape*, because the shape determines
the correct response — and responding with the wrong pattern usually makes things
worse.

| Shape | Characteristic | Correct response | Wrong response |
| --- | --- | --- | --- |
| **Transient** | Succeeds on retry | Bounded retry with jittered backoff | Failing immediately |
| **Persistent** | Fails identically every time | Fail fast, surface clearly | **Retrying** — amplifies load for a guaranteed failure |
| **Partial** | Some operations work, others do not | Degrade the affected capability only | Failing everything |
| **Saturation** | Works, but too slowly | Shed load, apply backpressure | **Retrying** — adds load to an overloaded system |
| **Byzantine** | Returns wrong results | Validate output; fail closed | Trusting the response |
| **Cascading** | One failure triggers others | Circuit-break at the boundary | Retrying through the cascade |

**The two rows marked as wrong responses are the same mistake** — retrying a
non-transient failure — and it is the most common resilience error. Retrying a
saturated dependency converts a slowdown into an outage; retrying a deterministic
failure spends capacity to fail identically. This is why D-358 prohibits it
rather than leaving it to judgment.

**Byzantine failure is specifically relevant to AI.** A model returning
confidently wrong output is not an error condition — there is no exception, no
status code, no signal. It is handled by schema validation (D-95) and the human
approval gate (D-36), which are resilience controls as much as quality controls.

---

## Resilience Patterns

| Pattern | Where applied | Prevents |
| --- | --- | --- |
| **Timeout** | Every network call, every DB statement (D-360) | Unbounded waits exhausting pools |
| **Bounded retry with jitter** | Transient failures only, one layer (D-356) | Retry storms and thundering herds |
| **Circuit breaker** | AI providers, external integrations, e-signature, payment | Hammering a known-dead dependency |
| **Bulkhead** | Separate worker pools, per-provider concurrency, connection pool partitioning | One workload exhausting shared capacity |
| **Fallback** | Alternate model provider, cache-on-source-failure, polling when WebSocket fails | Hard failure where degraded service is possible |
| **Load shedding** | Per-tenant rate limits, enqueue ceilings | Accepting work that cannot be completed |
| **Backpressure** | Every async path (D-363) | Unbounded queue growth then memory exhaustion |
| **Idempotency** | All mutations and consumers (D-361) | Retries and redelivery producing duplicates |
| **Graceful degradation** | Every degradable dependency (`29`) | Third-party failure becoming our outage |
| **Fail closed** | Tenant context, authorization, missing configuration | Insecure defaults under failure |

### Circuit breakers — where and why

**Decision.** Circuit breakers wrap every external dependency, per provider and
per tenant where applicable.

**Reasoning.** When a dependency is down, every request to it consumes a
connection, a worker slot and a timeout's worth of wall-clock before failing.
With enough concurrency, the *caller* exhausts its own resources waiting on a
dependency it already knows is broken — the classic cascade, where a third
party's outage becomes ours through no fault of our code.

A breaker makes the failure immediate and cheap: after a threshold of failures,
calls fail instantly without consuming resources, and a periodic probe tests
recovery.

**Per-provider, per-tenant granularity matters.** A single global breaker on "the
AI service" would open because one tenant's misconfigured integration is failing,
denying service to everyone. Breakers scoped to the actual failure domain contain
it.

**Trade-offs.** An open breaker rejects calls that might have succeeded, and
breaker thresholds are genuinely difficult to tune — too sensitive and it opens
on noise, too tolerant and it never protects. Tuned from observed failure rates,
reviewed as they change.

**Fail-closed versus fail-open per breaker**, decided deliberately:

| Breaker | On open | Reason |
| --- | --- | --- |
| Model provider | Fail over, then queue | Work is deferrable |
| Authorization service | **Fail closed — deny** | Never grant access on uncertainty |
| Cache | **Fail open — go to source** | Availability over latency (D-359) |
| Payment provider | Fail open — allow access, queue billing | Never gate product on billing (D-306) |
| Integration sync | Fail open — queue | Sync is eventually consistent by nature |

---

## The Degradation Matrix

**The central artifact of this document.** For each dependency failure: what
continues to work, what degrades, and what the user sees.

| Failed | Still works | Degraded | Unavailable | User sees |
| --- | --- | --- | --- | --- |
| **Model provider (all)** | Everything non-AI: read, edit, approve, plan, manage, export | — | AI generation | "AI assistance temporarily unavailable — queued and will run automatically" |
| **Model provider (primary only)** | Everything | Latency, possibly quality | — | Nothing |
| **AI service** | Everything non-AI | — | AI generation | As above; jobs remain queued |
| **Realtime gateway** | Everything | Live updates → polling | Presence | Slightly delayed updates; no error |
| **Redis cache** | Everything | Latency (cache miss to source) | — | Slower responses |
| **Redis queue** | All reads and direct edits | — | All async work: generation, export, sync, notification | "Background processing unavailable" — actions requiring it are blocked with a clear message |
| **Redis pub/sub** | Everything | Live updates → polling | — | Delayed updates |
| **PostgreSQL primary** | — | — | **Everything** | Maintenance page; automatic standby promotion (< 5 min) |
| **PostgreSQL replicas** | Everything | Reporting and search latency | — | Slower analytics |
| **Object storage** | Metadata operations, browsing, editing | — | Upload, download, export | Upload and export unavailable |
| **Search index** | Everything | — | Search | Search unavailable; navigation and traversal work |
| **Vector index** | Everything | AI grounding weaker (structural retrieval only) | Semantic search | Reduced generation quality — **flagged in output provenance** |
| **Customer IdP** | Existing sessions | — | New logins for that tenant | Break-glass admin path (D-300) |
| **Git provider** | Everything | Sync paused | Live repo state | Cached state shown with staleness indicator |
| **E-signature** | Contract generation and storage | — | Signature dispatch | "Sending for signature — queued" |
| **Payment provider** | Everything | — | Billing operations | Nothing user-visible |
| **Email/chat** | Everything | — | External notification | In-app notification remains authoritative |
| **Observability backend** | Everything | — | Our visibility | Nothing — **but we are blind** (D-305) |

**Three rows deserve comment.**

**PostgreSQL primary is the only single point of total failure**, and that is a
deliberate, stated consequence of choosing a single system of record (`06`).
Mitigated by multi-AZ synchronous standby with automatic promotion, not
eliminated. Honesty about this is better than a diagram implying redundancy that
does not exist.

**Redis queue failure blocks async work**, which is a larger blast radius than
cache failure and is precisely why the queue instance is configured `noeviction`
and separated (D-307). Users are told clearly rather than having actions silently
do nothing.

**Vector index failure degrades quality rather than availability**, and this is
the subtlest failure in the matrix: generation still works, output is still
produced, but it is less well grounded. Silent quality degradation is worse than
an outage because nobody notices. Hence the requirement that reduced grounding is
**flagged in the artifact's provenance** — the user sees that this draft was
generated with partial context.

---

## Data Recovery

| Scenario | Mechanism | Target |
| --- | --- | --- |
| Instance or AZ failure | Multi-AZ standby promotion | Automatic, < 5 min |
| Accidental deletion | Point-in-time recovery | RPO 15 min, RTO 4h (A-6/A-7) |
| Logical corruption from a bug | PITR to before the deploy + replay | Case by case |
| Single-tenant data loss | **Tenant-scoped restore to a staging database, then selective re-import** | Hours |
| Derived store loss | **Rebuild from system of record** (D-382) | Hours, no data loss |
| Blob deletion | Object versioning | Minutes |
| Event loss | Not possible by design — outbox (D-371) | — |

**Tenant-scoped restore is the operationally important case** and the one
generic backup strategies handle badly. Restoring the entire database to recover
one tenant's data would discard every other tenant's writes since the restore
point — unacceptable. The procedure restores to a separate instance, extracts the
tenant's rows, and re-imports selectively. It is slower and it is the only
correct approach in a shared-database model, which is a real cost of the tenancy
decision (`07`) and is stated rather than hidden.

**Derived stores are never restored from backup** — they are rebuilt from the
system of record. Restoring a search index from a backup risks reintroducing
stale state that diverges from the source; rebuilding guarantees consistency.

---

## Reconciliation

Eventual consistency and at-least-once delivery mean divergence is possible.
Reconciliation detects it rather than assuming it does not occur.

| Check | Detects | Frequency |
| --- | --- | --- |
| Outbox lag and unpublished age | Relay stalled | Continuous, alerted |
| Orphaned blob sweep | Blobs with no referencing row (D-389) | Daily |
| Dangling reference scan | Rows referencing missing blobs — **should be impossible** | Daily |
| Graph link integrity | Links referencing non-existent versions | Daily |
| Search index drift | Documents in the index absent from source, and vice versa | Daily |
| Vector index drift | Approved versions lacking embeddings | Daily |
| Job/artifact consistency | Completed jobs with no resulting artifact | Hourly |
| Cost reconciliation | Internal metering vs provider invoice (D-445) | Monthly |

**The dangling-reference scan is checking an invariant the design says cannot be
violated**, and that is exactly why it runs. An invariant nobody verifies is an
assumption; a violation would indicate a bug in the write-order discipline
(D-389), and finding it via a daily scan is far better than finding it via a
customer reporting a broken artifact.

---

## Failure Injection

Degradation paths that have never been exercised are hypotheses. Every row of the
degradation matrix is a claim requiring evidence.

| Scenario | Verifies | Cadence |
| --- | --- | --- |
| Model provider unreachable | A-5 — platform fully functional without AI | Per release |
| AI service down | Queued jobs resume on recovery | Per release |
| Redis cache flushed | Fail-open to source; no correctness change | Per release |
| Realtime gateway killed | Polling fallback engages | Per release |
| Database failover | Promotion time; connection recovery | Quarterly |
| Provider returns 429 sustained | Backpressure, no retry storm | Per release |
| Provider returns malformed output | Clean failure, no partial artifact | Per release (adversarial suite) |
| Worker killed mid-job | Redelivery; idempotency prevents duplication | Per release |
| Queue depth spike | Fairness holds; no tenant starvation | Per phase |
| Egress blocked | Circuit breakers open cleanly | Quarterly |

**Run in staging against production-shaped data**, not in production. Production
chaos engineering is a legitimate practice at a maturity and scale we do not
have; introducing deliberate failure into a system whose degradation paths have
never been tested is how a test becomes an incident.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-449 | Failures classified by shape; the shape determines the response | The same wrong response — retrying — turns saturation and persistent failure into outages |
| D-450 | Circuit breakers on every external dependency, scoped per provider and tenant | A global breaker denies everyone because one tenant's integration is broken |
| D-451 | Each breaker's open behaviour (closed/open) is decided deliberately | Failing closed on cache is an outage; failing open on authorization is a breach |
| D-452 | The degradation matrix is a required, maintained artifact | Undefined partial-failure behaviour is decided during incidents by whoever is on call |
| D-453 | PostgreSQL primary is acknowledged as the single point of total failure | Honesty beats a diagram implying redundancy that does not exist |
| D-454 | Silent quality degradation is surfaced in artifact provenance | An unflagged drop in grounding quality is worse than an outage because nobody notices |
| D-455 | Tenant-scoped restore via staging instance, never a full-database restore | A full restore discards every other tenant's writes since the restore point |
| D-456 | Derived stores are rebuilt, never restored from backup | Restoring risks reintroducing state that diverges from the source |
| D-457 | Reconciliation verifies invariants the design says cannot be violated | An unverified invariant is an assumption |
| D-458 | Every degradation matrix row is verified by failure injection | An untested degradation path is a hypothesis |
| D-459 | Failure injection runs in staging, not production | Injecting failure into untested degradation paths turns a test into an incident |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| A degradation path fails differently than documented | Incident response follows a wrong runbook | Failure injection per release; matrix corrected from observed behaviour |
| Circuit breaker thresholds mistuned | Opens on noise, or never protects | Tuned from observed failure rates; reviewed as traffic changes |
| Database failover exceeds its target | A-1 breached | Quarterly failover drills with recorded timings |
| Tenant-scoped restore never rehearsed | Slow, error-prone recovery during a real incident | Included in the quarterly restore drill rotation |
| Reconciliation findings ignored | Divergence accumulates silently | Findings raise tracked defects, not just log lines |
| Degradation matrix goes stale as dependencies change | Documented behaviour diverges from reality | Reviewed at phase boundaries; new dependencies require a matrix row |
| Retry-on-persistent-failure reintroduced by a well-meaning change | Load amplification during incidents | Retry classification centralized; asserted by test |

## Dependencies

- **Depends on:** system context (`29`), containers (`30`), communication
  (`34`), events (`35`), data (`36`), observability (`38`).
- **Depended on by:** deployment and incident response (`15`).

## Future Improvements

- Automate degradation matrix verification as part of the release pipeline
  rather than a manual per-release exercise.
- Add per-dependency SLO tracking so degradation frequency is measured, not
  merely handled.
- Reassess production failure injection once degradation paths have a year of
  verified staging results behind them.
