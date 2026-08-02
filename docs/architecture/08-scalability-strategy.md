# Scalability Strategy

## Purpose

Define how the platform grows from its first tenant to the Phase 4 targets in
`04` (50k tenants, 2M users, 5k rps) without rewrites, and — equally important —
define what we deliberately do *not* build yet.

## Scope

**In scope:** scaling approach per tier, data-tier strategy, async and AI
scaling, caching, per-tenant fairness, cost scaling, and the triggers that move
us from one stage to the next.

**Out of scope:** infrastructure provisioning (`docs/delivery/15-deployment-strategy.md`)
and isolation mechanics (`07`).

---

## Governing Principle

**Do not build for scale we do not have. Do not make decisions that foreclose
the scale we expect.**

These are different disciplines, and conflating them is how teams both
over-engineer and paint themselves into corners simultaneously. Sharding
Postgres in Phase 1 is waste. Writing a query that cannot be tenant-partitioned
is a mistake that costs a rewrite. This document distinguishes the two
throughout: *stateless from day one* (cheap now, impossible to retrofit),
*sharding later* (expensive now, tractable later because the design permits it).

Three properties are established immediately because retrofitting them is
prohibitive:

1. **Statelessness** in every request-handling tier (S-8).
2. **Tenant-partitionable data access** — every query is already tenant-scoped
   (`07`), which is precisely what makes future sharding mechanical.
3. **Asynchrony for anything slow** (P-9), so sync capacity is decoupled from
   work duration.

---

## Scaling by Tier

### Web tier (SPA)

Static assets on a CDN. Scales without intervention and costs almost nothing.
Constraint: bundle size budgets enforced in CI so growth does not silently
degrade P-4.

### Core API

**Horizontal, stateless.** No session affinity, no local state, no in-process
caching of shared data. Scaling is adding replicas behind the load balancer.

- **Scaling signal:** request concurrency and latency percentiles, not CPU.
  CPU-based autoscaling reacts late for IO-bound workloads — by the time CPU
  rises, latency has already breached P-1.
- **Ceiling:** database connections, not compute. Addressed by connection
  pooling with a proxy, which becomes necessary well before the API tier itself
  is stressed.

**Why stateless is non-negotiable:** any in-process state — a cached
permission set, a local rate-limit counter, a user session — produces
inconsistent behaviour across replicas and silently caps horizontal scaling.
This is the single cheapest discipline to maintain now and the most expensive
to retrofit later.

### Realtime gateway

Scales on **concurrent connections**, an entirely different signal from the API
tier — which is exactly why it is a separate component (D-32). Connection state
is externalized to Redis so any instance can serve any client and a deploy does
not lose subscription state.

### Workers

Scale on **queue depth and age**. Queue age is the better signal: depth alone
does not distinguish 1,000 fast jobs from 10 slow ones, while age directly
measures the user-visible symptom.

Separate worker pools by workload class — AI jobs, integration sync, exports,
notifications — so a backlog in one class cannot starve another. A single
undifferentiated pool means one slow integration partner delays every user's
document generation.

### AI orchestration service

Scales on **in-flight inference concurrency**. Its true ceiling is usually the
*provider's* rate limit, not our compute, so scaling out our replicas past that
point achieves nothing except more 429s. Handled by:

- Provider-aware concurrency limits and token-bucket throttling
- Request queueing with backpressure to workers rather than blind retry
- Multi-provider routing to spread load across independent limits
- Exponential backoff with jitter on 429s

**This is a rate-limit management problem disguised as a scaling problem**, and
treating it as the latter leads to spending money on capacity that cannot be
used.

---

## Data Tier — Staged Strategy

The data tier is where scaling decisions are expensive, so the stages and their
triggers are defined in advance rather than improvised under pressure.

### Stage 1 — Single primary (Phase 1–2)

One Postgres primary with a standby for HA. Sufficient to roughly 1k tenants.

**Focus:** get the schema right. Indexes leading with `tenant_id`, appropriate
JSONB indexing, no N+1 query patterns, connection pooling from the start.

Most "scaling problems" at this stage are missing-index problems. Query
performance monitoring belongs here, not later.

### Stage 2 — Read replicas (trigger: read load > 60% of primary capacity)

Route analytics, reporting, search and heavy graph traversals to replicas.

**Constraint:** replica lag makes reads eventually consistent. Any read that
must observe its own write — the read immediately after an approval, for
instance — must be explicitly routed to the primary. This is a correctness
concern, not a performance one, and must be an explicit decision per read path
rather than a global default.

### Stage 3 — Partitioning (trigger: largest tables exceed ~100M rows)

Table partitioning on the highest-volume tables (graph edges, audit log,
telemetry) by time or by tenant range. Improves query pruning, and makes
retention enforcement a partition drop instead of a mass delete.

### Stage 4 — Tenant sharding (trigger: write throughput > 70% of a
well-provisioned primary)

Tenants distributed across multiple database clusters, routed by tenant ID.

**Tractable only because of the discipline established in Stage 1**: every
query is tenant-scoped, no query spans tenants, and no application code assumes
a single connection. Cross-tenant analytics moves to a separate aggregate store
fed by events — which is already the design (D-16), so nothing needs to change
there either.

**Deliberately deferred:** sharding adds routing complexity, rebalancing, and
cross-shard operational pain. A well-provisioned Postgres primary handles far
more than most teams assume, and the migration is tractable *because* the
groundwork is laid. Building it now would be textbook premature optimization.

### Vector storage

pgvector alongside the primary initially (D-45). Trigger for a dedicated vector
store: retrieval latency breaching P-6's budget, or vector operations
materially competing with transactional load. Retrieval sits behind an
interface specifically so this migration touches one adapter.

---

## Caching Strategy

Layered, each with an explicit invalidation story. **A cache without a defined
invalidation strategy is a bug with a latency benefit.**

| Layer | Contents | Invalidation |
| --- | --- | --- |
| CDN | Static assets | Content-hashed filenames; immutable |
| HTTP | Cacheable GETs | ETag / conditional requests |
| Application (Redis) | Permission sets, tenant config, reference data | Event-driven on write |
| Query result | Expensive aggregates, graph traversals | TTL + event-driven |
| AI response | Deterministic-input generations | Content-hash keyed |

**Rules:**

- Tenant ID is structurally part of every cache key (D-58).
- Caches are never a system of record (D-46).
- Event-driven invalidation is preferred over TTL where correctness matters;
  TTL alone means serving stale permissions after a revocation — a security
  problem, not a freshness problem.

**AI response caching deserves specific attention.** It is the highest-value
cache in the system because it saves money as well as latency ($-2). It applies
only where inputs are genuinely identical (same prompt version, same context
hash, same model, same parameters) — and identical inputs are more common than
expected, especially for classification and extraction. It must never be shared
across tenants, regardless of input similarity.

---

## Asynchronous Processing

The primary scaling lever in the system: it converts unbounded work duration
into bounded request latency.

- All AI generation, exports, integration sync, notifications and bulk
  operations are queued (P-9, D-35).
- Jobs are idempotent (D-37) — at-least-once delivery makes retries certain.
- Failures use bounded exponential backoff and land in a dead-letter queue with
  alerting. A silently discarded job is a data-integrity bug.
- Long jobs report progress so the UI is honest about what is happening; a
  five-minute operation with no feedback reads as a broken product.

**Queue fairness** (D-62) is a scalability requirement as much as a tenancy
one: per-tenant partitioning with round-robin consumption prevents one tenant's
bulk work from becoming everyone's outage.

---

## Cost Scaling

Cost is an architectural property in an AI product ($-1 to $-4), and it does
**not** scale linearly with usage unless designed to.

| Driver | Control |
| --- | --- |
| AI inference | Task-tier model routing; caching; context-size discipline; per-tenant caps |
| Database | Right-sizing, index efficiency, partition-based retention |
| Storage | Lifecycle policies moving old artifacts to cold tiers |
| Egress | CDN offload; avoid chatty cross-service traffic |
| Observability | Sampling on high-volume traces; retention tiers |

**Two costs routinely surprise teams and are called out deliberately:**

1. **Context size.** Grounding context assembled from the graph directly
   determines token cost on *every* call. Retrieving 50 documents when 5 would
   do multiplies cost tenfold for no quality gain — often for a quality *loss*,
   since irrelevant context degrades output. Retrieval precision is a cost
   control, not just a quality control.
2. **Observability at scale.** Full-fidelity tracing at 5k rps can rival
   application infrastructure cost. Sampling strategy is a Phase 3 requirement,
   with error and slow-request traces always retained.

---

## Scaling Triggers and Actions

Defined in advance so scaling is a planned action against a threshold rather
than an incident response.

| Signal | Threshold | Action |
| --- | --- | --- |
| API p95 latency | > 80% of P-1 budget | Add replicas; profile hot paths |
| DB CPU | > 60% sustained | Query review; add read replicas |
| DB connections | > 70% of limit | Tune pooler; reduce per-instance pool size |
| Queue age | > 5 min for user-facing jobs | Add workers in the affected class |
| Provider 429 rate | > 1% of calls | Increase concurrency limits or spread across providers |
| Largest table | > 100M rows | Plan partitioning |
| Write throughput | > 70% of primary capacity | Begin shard planning |
| Per-tenant load | > 5% of platform total (S-9) | Review quotas; consider promotion to dedicated resources |
| AI cost per artifact | Rising for 2 consecutive weeks | Review routing, caching and context size |

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-65 | Stateless request tiers from day one | Cheap now, impossible to retrofit; the precondition for all horizontal scaling |
| D-66 | Autoscale on latency and concurrency, not CPU | CPU reacts too late for IO-bound workloads |
| D-67 | Separate worker pools by workload class | Prevents one slow class starving all others |
| D-68 | AI scaling treated as provider rate-limit management | Adding replicas past the provider limit buys nothing |
| D-69 | Staged data strategy with pre-defined triggers | Avoids both premature sharding and panic sharding |
| D-70 | Replica reads are opt-in per read path, not the default | Read-after-write correctness must be an explicit decision |
| D-71 | Every cache has a defined invalidation strategy | TTL-only caching of permissions is a security bug |
| D-72 | AI responses cached on identical inputs, never across tenants | Highest-value cache for both latency and cost |
| D-73 | Retrieval precision treated as a cost control | Context size drives token cost on every single call |
| D-74 | Sharding deferred until write throughput demands it | Tractable later precisely because tenant scoping is enforced now |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| In-process state introduced accidentally | Silent horizontal scaling cap; inconsistent behaviour | Architecture tests; multi-replica testing in CI |
| Replica lag causes read-after-write bugs | Users see stale data after their own action | Explicit primary routing for read-after-write paths; documented per path |
| Provider rate limits cap throughput regardless of our scaling | AI features degrade under load | Multi-provider routing; backpressure; negotiated limit increases ahead of growth |
| Sharding needed sooner than expected | Migration under pressure | Triggers monitored continuously; tenant-partitionable design maintained from day one |
| AI cost scales faster than revenue | Margin collapse (G5) | Per-tenant metering from Phase 1; caps in Phase 2; routing and caching |
| Cache invalidation bugs serve stale permissions | Security exposure | Event-driven invalidation for anything security-relevant; never TTL alone |
| Observability cost grows with traffic | Unexpected infrastructure spend | Sampling strategy in Phase 3; retention tiers |

## Dependencies

- **Depends on:** NFRs (`04`), architecture (`05`), technology (`06`), tenancy (`07`).
- **Depended on by:** deployment strategy, testing strategy (load testing), AI
  strategy (cost controls).

## Future Improvements

- Establish load-test baselines in Phase 2 and re-derive every threshold above
  from measurement rather than estimation.
- Define the shard routing and rebalancing design before write throughput
  reaches 50% of primary capacity — design early, implement late.
- Model per-tenant unit economics once real usage distribution is known;
  current cost assumptions are unvalidated.
