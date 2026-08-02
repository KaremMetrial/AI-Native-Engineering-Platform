# Caching and Queueing Architecture

## Purpose

Define the cache topology, key design and invalidation model; and the queue
topology, fairness, retry and priority model. Both are Redis-backed but
operationally distinct (D-307), and both are places where a convenient shortcut
produces either a correctness defect or silent work loss.

## Scope

**In scope:** cache layers, key design, invalidation, stampede protection, what
must never be cached; queue topology, per-tenant fairness, priority, retry,
visibility and dead-lettering.

**Out of scope:** caching *policy* and when to cache at all (`26`), container
specifications (`30`).

---

# Part 1 · Caching

## Cache Layers

Five layers, each with a distinct scope, TTL and invalidation trigger.

| Layer | Location | Contents | Invalidation |
| --- | --- | --- | --- |
| **CDN** | Edge | Static assets | Content-hashed filenames — immutable, never invalidated |
| **HTTP** | Client + edge | Cacheable GETs | ETag / conditional requests |
| **Application** | Redis cache | Permissions, tenant config, entitlements, reference data | **Event-driven** |
| **Query result** | Redis cache | Expensive aggregates, graph traversals | Event-driven + TTL |
| **AI response** | Redis cache + object storage | Generation results for identical inputs | Content-addressed — never invalidated |

**AI response caching is the highest-value layer** (D-72, D-263) because it saves
money as well as latency. It is also the safest, because the key includes the
complete input — prompt version, model, parameters, context hash and tenant — so
there is no staleness question. A cache hit is provably the same computation.

---

## Key Design

**Decision.** Cache keys are structured, tenant-prefixed, and carry a namespace
version that enables mass invalidation without scanning.

```
   {namespace}:{namespaceVersion}:{tenantId}:{entity}:{identifier}[:{variant}]

   perms:v3:t_4471:user:u_882
   config:v1:t_4471:tenant:settings
   graph:v2:t_4471:traversal:a_5510:depth3
   ai:v1:t_4471:brd_synthesis:sha256(promptV3+model+contextHash)
```

**Why the namespace version matters.** Invalidating a whole class of cached data
— after a permissions model change, a bug fix, or a deploy that changes a
computation — requires either scanning for matching keys (an O(n) operation that
blocks Redis) or tracking every key ever written (bookkeeping that itself needs
invalidating). Bumping a namespace version makes every old key unreachable
instantly, at O(1), and lets them expire naturally by TTL.

**Why the tenant prefix is structural, not conventional** (D-58): the key builder
requires a tenant identifier, so producing a cross-tenant key is not an available
operation. A convention that "keys should include the tenant" is a convention
that will be violated once, and once is enough.

**Alternatives.** *Flat keys with prefix scanning for invalidation* — simple
until a `SCAN` over millions of keys blocks the instance. *Tag-based
invalidation* — genuinely more precise and requires maintaining tag→key sets,
which is a second data structure with its own consistency problem. Revisit if
namespace-version granularity proves too coarse.

**Trade-offs.** Namespace bumping over-invalidates — it discards valid entries
alongside stale ones, producing a cold-cache period. Acceptable because it is
rare and correctness-preserving.

---

## Invalidation

**Decision.** Event-driven invalidation is primary; TTL is a backstop, never the
sole mechanism for anything security-relevant.

| Cached data | Invalidated by | TTL backstop |
| --- | --- | --- |
| User permissions | `MembershipRevoked`, `MembershipGranted`, role change | 5 min |
| Tenant configuration | Configuration change events | 15 min |
| Entitlements | Subscription change events | 15 min |
| Graph traversal results | `ArtifactVersionCreated`, `LinkCreated` for the subtree | 5 min |
| Reference data | Deploy / namespace bump | 24 h |
| AI responses | Never — content-addressed | 30 days |

**Reasoning.** A TTL-only cache of permissions serves revoked access until it
expires (D-71). With a 15-minute TTL, a user removed for cause retains access for
up to fifteen minutes — an unacceptable security property, and one that is
invisible because everything appears to work.

Event-driven invalidation makes revocation effective in the time it takes the
outbox relay to publish, typically under a second. **The TTL remains as a
backstop** because event delivery can fail, and a bounded staleness window is
better than unbounded.

**The subtlety worth stating:** invalidation must be tenant-scoped and must not
require the invalidating context to know every consumer. Contexts subscribe to
`MembershipRevoked` and invalidate their own caches; no context maintains a
registry of who caches what.

---

## Stampede Protection

When a hot key expires under load, every concurrent request misses simultaneously
and recomputes the same value — a thundering herd against the source, at exactly
the moment of highest traffic.

| Mechanism | Applies to |
| --- | --- |
| **Single-flight** — one computation, others await its result | All expensive computations |
| **Probabilistic early expiry** — recompute slightly early with rising probability as TTL approaches | Hot keys with predictable access |
| **Stale-while-revalidate** — serve stale briefly while refreshing in background | Non-security-relevant data only |
| **Negative caching** — cache "not found" with a short TTL | Lookups where misses are common |

**Stale-while-revalidate is explicitly prohibited for permissions and
entitlements.** Serving known-stale authorization data, even briefly, is the
failure this section exists to prevent.

**Negative caching matters more than it appears:** without it, a repeated request
for a non-existent resource hits the database every time, which is a trivial
denial-of-service vector.

---

## What Must Never Be Cached

| Never cached | Reason |
| --- | --- |
| Cross-tenant aggregates in a tenant-keyed cache | Structural leak |
| AI responses across tenants (D-82) | Content-hash-only keys are a direct cross-tenant leak |
| Anything with a TTL-only strategy that gates access | Revocation becomes ineffective |
| Secrets, tokens, credentials | Redis is not a secret store; no encryption at rest guarantee for this use |
| Personally identifiable data beyond what the request needs | Expands the breach surface for marginal benefit |

---

# Part 2 · Queueing

## Queue Topology

**Decision.** Queues are partitioned by workload class *and* priority, with
per-tenant fairness applied within each.

```
   ┌─────────────────────────────────────────────────────────┐
   │  AI JOBS                                                 │
   │  ├── ai.interactive   (user is waiting)     priority 1   │
   │  └── ai.batch         (bulk generation)     priority 3   │
   ├─────────────────────────────────────────────────────────┤
   │  INTEGRATION SYNC                                        │
   │  ├── sync.webhook     (inbound, time-sensitive) prio 2   │
   │  └── sync.poll        (scheduled reconciliation) prio 4  │
   ├─────────────────────────────────────────────────────────┤
   │  DOCUMENTS                                               │
   │  └── export           (PDF, bulk export)        prio 3   │
   ├─────────────────────────────────────────────────────────┤
   │  NOTIFICATIONS                                           │
   │  └── notify           (email, chat, in-app)     prio 2   │
   ├─────────────────────────────────────────────────────────┤
   │  MAINTENANCE                                             │
   │  └── maintenance      (retention, reindex)      prio 5   │
   └─────────────────────────────────────────────────────────┘
                    │
              ┌─────▼──────┐
              │    DLQ     │  one per source queue, envelope preserved
              └────────────┘
```

**Why class *and* priority rather than priority alone.** A single priority-ordered
queue means a flood of priority-1 work starves everything else, and a slow
consumer on one work type blocks the queue for all types. Separate queues per
class give each its own consumer pool and its own failure domain (D-311);
priority orders work *within* a pool.

**Why `ai.interactive` and `ai.batch` are separate queues rather than priorities
in one.** A tenant enqueuing 500 documents for bulk generation would otherwise
sit ahead of every subsequent interactive request in arrival order. Separating
them means an interactive request never waits behind batch work, regardless of
priority arithmetic.

---

## Per-Tenant Fairness

**Decision.** Within each queue, consumption is round-robin across tenants with
pending work, not first-in-first-out.

**Reasoning.** FIFO across a multi-tenant queue means one tenant's bulk operation
delays every other tenant behind it (D-62). A small customer's single urgent
document waits an hour behind someone else's batch — the noisy-neighbour failure
mode that S-9 exists to prevent, expressed at the queue layer.

**Mechanism.** Per-tenant sub-queues within each class; the consumer selects the
next tenant round-robin among those with pending work, weighted by plan tier
where entitlements justify it. A tenant with 500 queued jobs and a tenant with 1
each get a turn.

**Alternatives.** *Global FIFO* — simplest and produces exactly the starvation
described. *Strict per-tenant rate limiting only* — prevents monopolization
without ensuring fairness among tenants under the limit. *Weighted fair queueing
with dynamic weights* — more precise, more complex, and unnecessary until usage
patterns are known.

**Trade-offs.** More bookkeeping per queue, and round-robin adds a small
selection cost per dequeue. Negligible relative to job execution time.

**Long-term impact.** This is the mechanism that keeps small customers viable on
shared infrastructure. Without it, platform quality of service is proportional to
how quiet your neighbours are, which is not a property anyone can sell.

---

## Job Lifecycle

```
   enqueue ──▶ pending ──▶ leased ──▶ processing ──┬──▶ completed
                              ▲                     │
                              │                     ├──▶ failed ──▶ retry
                              │                     │       (bounded, backoff+jitter)
                              └── visibility ───────┘
                                  timeout expiry    └──▶ exhausted ──▶ DLQ + alert
```

| Property | Value | Reason |
| --- | --- | --- |
| **Lease / visibility timeout** | Per queue: 60 s notify, 600 s AI | Must exceed the job's realistic duration or the job is redelivered mid-execution |
| **Lease renewal** | Long jobs heartbeat to extend | Avoids a duration-based ceiling on job length |
| **Max attempts** | 3 (interactive), 5 (background) | Bounded — infinite retry is a load amplifier |
| **Backoff** | Exponential with full jitter (D-357) | Unjittered retries synchronize into a herd |
| **Idempotency** | Job ID as the natural key (`34`) | At-least-once makes duplicates certain |

**Visibility timeout tuning is the most common queue bug.** Set shorter than the
job's real duration, the job is redelivered while still running — producing
duplicate work, duplicate AI spend, and, without idempotency, duplicate
artifacts. Set far too long, a genuinely crashed job is invisible for the
duration. Heartbeat-based lease renewal removes the trade-off for long jobs.

**Retries only for retryable failures** (D-358): provider 429s, timeouts,
transient connection errors. A validation failure or a business-rule rejection is
deterministic — retrying it consumes capacity to fail identically.

---

## Rate-Limited Queues

AI jobs are constrained by provider rate limits, not by our capacity (D-68).
Adding workers past the provider's limit produces more 429s, not more throughput.

**Mechanism:** a token-bucket per provider, shared across workers via Redis.
A worker acquires a token before dispatching; when the bucket is empty it waits
rather than issuing a request destined for rejection. This converts rate limiting
from a *reactive* concern (handle 429s) into a *proactive* one (do not exceed the
limit), which is both more efficient and gentler on the provider.

---

## Dead Letter Handling

| Property | Requirement |
| --- | --- |
| Scope | One DLQ per source queue — mixing them loses the failure context |
| Contents | **Full job payload plus failure history** — a DLQ entry without its payload is unrecoverable |
| Alerting | Every entry alerts; not a threshold (D-378) |
| Replay | Explicit, audited, after remediation |
| Retention | 30 days, then archived to object storage |

**A silently growing DLQ is silent work loss**, and it is the failure mode that
most often goes unnoticed because nothing is visibly broken — the user simply
never receives what they asked for.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-399 | Cache keys carry a namespace version enabling O(1) mass invalidation | Prefix scanning blocks Redis; key registries need their own invalidation |
| D-400 | Tenant prefix is produced structurally by the key builder | A convention will be violated once, and once is enough |
| D-401 | Event-driven invalidation primary; TTL is a backstop, never sole | TTL-only permission caching serves revoked access until expiry |
| D-402 | Contexts invalidate their own caches on subscribed events | No context maintains a registry of who caches what |
| D-403 | Single-flight on all expensive recomputation | Hot-key expiry under load is a thundering herd against the source |
| D-404 | Stale-while-revalidate prohibited for permissions and entitlements | Serving known-stale authorization is the failure this prevents |
| D-405 | Negative caching on miss-prone lookups | Otherwise repeated non-existent lookups are a trivial DoS vector |
| D-406 | Queues partitioned by workload class and priority, not priority alone | Priority alone lets a flood starve other classes and a slow consumer block all |
| D-407 | `ai.interactive` and `ai.batch` are separate queues | Otherwise bulk work sits ahead of every later interactive request |
| D-408 | Round-robin per-tenant consumption within each queue | FIFO lets one tenant's batch delay every other tenant |
| D-409 | Visibility timeout exceeds realistic job duration; long jobs renew by heartbeat | Too short redelivers mid-execution; too long hides crashes |
| D-410 | Provider rate limits enforced proactively by shared token bucket | Reactive 429 handling wastes capacity and pressures the provider |
| D-411 | One DLQ per source queue, full payload preserved, alert on every entry | Mixed DLQs lose context; payload-less entries are unrecoverable |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Namespace bump causes a cold-cache latency spike | Temporary P-1 breach | Bumps are rare and scheduled; single-flight limits the source load |
| Event-driven invalidation misses a consumer | Stale permissions beyond intent | TTL backstop bounds the window; isolation tests cover revocation |
| AI cache key omits an input dimension | Wrong result served as a hit | Key includes prompt version, model, parameters, context hash and tenant; asserted by test |
| Round-robin bookkeeping becomes a dequeue bottleneck | Throughput ceiling | Bookkeeping is O(active tenants) per queue; monitored, with weighted fair queueing as the escalation |
| Visibility timeout mistuned after job duration changes | Duplicate work and spend | Timeout derived from measured p99 duration and reviewed when handlers change |
| Token bucket state lost on Redis failure | Burst exceeding provider limits | Bucket refills conservatively on restart; provider 429 handling remains as the backstop |
| DLQ replay reintroduces the original failure | Retry loop | Replay requires a recorded remediation; replayed jobs are tagged and monitored |

## Dependencies

- **Depends on:** containers (`30`), communication (`34`), events (`35`),
  scalability (`08`), performance (`26`).
- **Depended on by:** AI integration (`40`), resilience (`41`).

## Future Improvements

- Derive visibility timeouts from measured p99 job durations rather than
  estimates, once Phase 1 telemetry exists.
- Evaluate tag-based cache invalidation if namespace granularity proves too
  coarse in practice.
- Add per-tenant queue wait-time telemetry so fairness is measured rather than
  assumed.
