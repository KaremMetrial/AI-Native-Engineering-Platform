# Container Architecture (C4 Level 2)

## Purpose

Decompose the platform into deployable, independently runnable units — their
responsibilities, technology, interfaces, scaling drivers, state and failure
impact. `05` established the architectural style; this document specifies the
containers that implement it.

## Scope

**In scope:** every deployable and data container, its contract with the others,
and the decisions about where container boundaries fall.

**Out of scope:** internals of each container (`31`) and infrastructure
provisioning (`15`).

---

## Container Diagram

```
                          ┌──────────────┐
   Users ────────────────▶│  CDN + WAF   │ Zone 1
                          └──────┬───────┘
                                 │
                          ┌──────▼───────┐
                          │Load Balancer │
                          └──┬────────┬──┘
              ┌──────────────┘        └───────────────┐
              │                                       │
       ┌──────▼───────┐                      ┌────────▼────────┐
       │   Core API   │                      │    Realtime     │
       │  (Laravel)   │◀────────────────────▶│    Gateway      │
       │  stateless   │                      │   (WebSocket)   │
       └──┬───┬───┬───┘                      └────────┬────────┘
          │   │   │                                   │
          │   │   └──────────── publish ──────┐       │ subscribe
          │   │                               │       │
          │   └────── read/write ───┐         │       │
          │                          │        │       │
    ┌─────▼──────┐          ┌────────▼──┐  ┌──▼───────▼──┐  ┌──────────┐
    │ PostgreSQL │          │Redis Cache│  │ Redis Pub/  │  │  Redis   │
    │ primary +  │          │ (evictable│  │ Sub + Conn  │  │  Queue   │
    │ replicas   │          │  volatile)│  │  Registry   │  │(durable) │
    └─────┬──────┘          └───────────┘  └─────────────┘  └────┬─────┘
          │                                                       │
          │                     ┌─────────────────────────────────┘
          │                     │ consume
          │              ┌──────▼───────────────────────────┐
          │              │        Worker Fleet              │
          └──────────────│  ┌────────┬────────┬──────────┐  │
             read/write  │  │AI Jobs │ Sync   │ Exports  │  │
                         │  │ pool   │ pool   │ + Notify │  │
                         │  └────────┴────────┴──────────┘  │
                         └──────┬───────────────────────────┘
                                │ HTTP + SSE
                         ┌──────▼───────────┐        ┌──────────────┐
                         │ AI Orchestration │───────▶│    Model     │
                         │ (Python/FastAPI) │        │  Providers   │
                         └──────────────────┘        └──────────────┘

    ┌──────────────┐   ┌──────────────┐   ┌────────────────────┐
    │  Scheduler   │   │   Object     │   │  OTel Collector    │
    │ (leader-     │   │   Storage    │   │  → Observability   │
    │  elected)    │   │   (S3)       │   │     Backend        │
    └──────────────┘   └──────────────┘   └────────────────────┘
```

---

## Container Specifications

### Edge — CDN + WAF

| | |
| --- | --- |
| **Responsibility** | TLS termination, static asset delivery, DDoS and bot mitigation, OWASP rule enforcement, coarse rate limiting |
| **Technology** | Managed CDN with WAF |
| **State** | Cached assets only |
| **Scaling** | Managed |
| **Interfaces** | In: HTTPS from Zone 0. Out: HTTPS to load balancer |
| **Failure impact** | Total outage — accepted, as this tier is managed, globally distributed and outside our failure domain |

### Web Application — SPA

| | |
| --- | --- |
| **Responsibility** | All user interaction; streaming result rendering; optimistic UI; graph visualization |
| **Technology** | React + TypeScript, built with Vite, served as static assets |
| **State** | Client-side only; server state cached in the data layer, never duplicated into local state |
| **Scaling** | CDN-served; effectively free |
| **Interfaces** | Out: REST to Core API, WSS to Realtime Gateway |
| **Failure impact** | None server-side; a bad build is reverted by CDN rollback |

**Contains no business rules.** Every authorization decision, validation
invariant and domain rule is enforced server-side; the client's copies exist for
responsiveness and are never authoritative.

### Core API

| | |
| --- | --- |
| **Responsibility** | Domain logic, authorization, transactional writes, graph operations, job enqueueing, outbox writes |
| **Technology** | PHP 8.4 + Laravel, modular monolith (`05`) |
| **State** | **Stateless.** No sessions, no local cache of shared data, no sticky routing (D-65) |
| **Scaling** | Horizontal on request concurrency and latency, not CPU (D-66) |
| **Interfaces** | In: REST/JSON from LB. Out: PostgreSQL, Redis cache, Redis queue, object storage |
| **Failure impact** | Instance loss is absorbed by the load balancer; total loss is an outage |
| **Constraint** | **Never calls the AI service synchronously** (P-9) — it enqueues |

### Realtime Gateway

| | |
| --- | --- |
| **Responsibility** | WebSocket connection lifecycle, subscription management, push of job progress and streamed AI output, presence |
| **Technology** | Dedicated process, separate deployable |
| **State** | Connection state in-process; **subscription registry externalized to Redis** so any instance serves any client |
| **Scaling** | Horizontal on concurrent connections — a different signal from the API tier (D-32) |
| **Interfaces** | In: WSS from clients. Out: Redis pub/sub, Core API for authorization |
| **Failure impact** | Clients lose live updates and fall back to polling. **Degradable, not critical** |

**Why the subscription registry is external:** with in-process subscriptions, a
deploy drops every client's subscription and a rolling restart produces a
thundering herd of re-subscriptions. Externalizing it means an instance can die
without losing routing information, and clients reconnect to any instance.

**Fallback to polling is a deliberate design requirement**, not a degraded
accident: the Realtime Gateway must never be on the critical path for
correctness, only for immediacy.

### Worker Fleet

| | |
| --- | --- |
| **Responsibility** | All asynchronous work: AI job coordination, integration sync, exports, notifications, scheduled processing |
| **Technology** | Same codebase as Core API, different entrypoint (D-31) |
| **State** | Stateless; job state in PostgreSQL, progress in Redis |
| **Scaling** | Horizontal per pool, on queue **age** rather than depth (`08`) |
| **Interfaces** | In: Redis queue. Out: PostgreSQL, AI service, external systems, object storage |
| **Failure impact** | Work queues rather than failing; user-visible as delay |

**Separate pools per workload class** (D-67), deployed as distinct scalable units:

| Pool | Workload | Why isolated |
| --- | --- | --- |
| **AI jobs** | Generation, evaluation, embedding | Long-running, provider-rate-limited; must not be starved by fast work |
| **Integration sync** | Git, issue tracker, webhooks | Bound by third-party latency and rate limits |
| **Exports & documents** | PDF, bulk export | CPU and memory heavy; would otherwise evict other work |
| **Notifications** | Email, chat, in-app | Short, high-volume, latency-sensitive |

A single undifferentiated pool means one slow integration partner delays every
user's document generation — the failure mode this decomposition exists to
prevent.

### Scheduler

| | |
| --- | --- |
| **Responsibility** | Time-triggered work: recurring sync, retention enforcement, report generation, evaluation runs |
| **Technology** | Same codebase, scheduler entrypoint, **leader-elected** |
| **State** | Leader lock in Redis; schedule state in PostgreSQL |
| **Scaling** | Exactly one active instance; standbys for failover |
| **Failure impact** | Scheduled work delayed until failover, then catches up |

**Leader election is the point of this container existing.** Running the
scheduler inside every API replica means N replicas each firing every cron
job — duplicate exports, duplicate notifications, duplicate AI spend. Separating
it and electing a leader makes single execution structural rather than a matter
of configuration discipline.

**Scheduled jobs must be idempotent regardless**, because failover can produce
a brief double-execution window. Leader election reduces the probability;
idempotency handles the residue.

### AI Orchestration Service

| | |
| --- | --- |
| **Responsibility** | Workflow execution, context assembly, retrieval, prompt management, provider routing, guardrails, evaluation, cost metering |
| **Technology** | Python 3.12 + FastAPI, async |
| **State** | Stateless per request; workflow state persisted to PostgreSQL |
| **Scaling** | Horizontal on in-flight inference concurrency — ceiling is usually the provider's rate limit, not our compute (D-68) |
| **Interfaces** | In: HTTP + SSE from workers. Out: model providers, PostgreSQL (read-only for retrieval), object storage |
| **Failure impact** | AI features unavailable; **all non-AI function continues** (A-5) |

**Database access is read-only and retrieval-scoped.** The AI service does not
write domain state — the worker that invoked it persists the result. This keeps
the transactional boundary inside the Core API's domain model and means a
compromised or malfunctioning AI service cannot corrupt the system of record.

### PostgreSQL

| | |
| --- | --- |
| **Responsibility** | System of record: tenancy, artifacts, graph, jobs, outbox, audit |
| **Technology** | PostgreSQL 16+, managed, primary + synchronous standby + read replicas |
| **State** | Authoritative |
| **Scaling** | Vertical, then read replicas, then partitioning, then tenant sharding (`08`) |
| **Interfaces** | In: from Zone 2 only, via connection pooler |
| **Failure impact** | **Total outage.** Mitigated by multi-AZ standby with automatic promotion |
| **Constraint** | Application role has no `BYPASSRLS` and does not own tables (D-55) |

### Redis — three separate instances

**Decision.** Cache, queue and pub/sub run as **separate Redis instances** with
different configurations, not as one shared instance with different key
prefixes.

**Reasoning.** They have incompatible operational requirements, and sharing one
instance means the strictest requirement governs all of them — or, worse, the
loosest does.

| Instance | Eviction | Persistence | Failure impact |
| --- | --- | --- | --- |
| **Cache** | `allkeys-lru` — eviction is correct behaviour | None needed | Cache miss storm; degraded latency, no data loss |
| **Queue** | **`noeviction`** — evicting a job loses work | AOF | Job loss — unacceptable, hence separate |
| **Pub/Sub + connection registry** | `volatile-ttl` | None | Realtime degrades to polling |

**Alternatives considered.** *One instance, prefixed keys* — cheaper and
operationally simpler, and a memory-pressure event would evict queued jobs
because the eviction policy cannot differ per key. That is silent, unrecoverable
work loss, discovered as "the export never arrived." *Managed queue service from
day one* — correct eventually (`06` plans the SQS migration) and unnecessary
infrastructure at Phase 1 volumes.

**Trade-offs.** Three instances to provision, monitor and pay for instead of
one. Modest cost, and each is small.

**Benefits.** Cache pressure cannot destroy queued work. Each instance is sized
and tuned for its actual access pattern. Failure of one is contained.

**Long-term impact.** This is a boundary that is nearly free to establish now and
requires a migration under memory pressure later — exactly the kind of
foreclosing decision P10 identifies.

### Object Storage

| | |
| --- | --- |
| **Responsibility** | Uploaded documents, generated exports, large artifact payloads, backups |
| **Technology** | S3-compatible, versioning enabled, lifecycle policies |
| **State** | Authoritative for blob content |
| **Interfaces** | Written by Core API and workers; read by clients via short-lived signed URLs |
| **Failure impact** | Uploads and exports unavailable; metadata operations continue |

**Clients never receive long-lived URLs**, and never read through the API for
large objects — signed URLs with short expiry keep bulk transfer off the
application tier while preserving authorization.

### OpenTelemetry Collector

| | |
| --- | --- |
| **Responsibility** | Receive, batch, sample and forward traces, metrics and logs |
| **Technology** | OTel Collector, deployed per node |
| **State** | In-memory buffer with disk spill |
| **Failure impact** | Telemetry loss — **visibility, not function** (D-305) |

Sitting between applications and the backend is what makes the backend
replaceable (D-50) and gives one place to enforce redaction, sampling and
cardinality limits before data leaves.

---

## Container Boundary Decisions

### Why Realtime is separate from Core API

**Reasoning.** WebSocket connections are long-lived and stateful; HTTP requests
are short and stateless. Sharing a process means a routine API deploy drops every
open connection, and connection count rather than request rate becomes the
scaling constraint for the entire API tier.

**Alternatives.** *Same process* — one fewer deployable, and couples deploy
cadence to connection stability. *Managed realtime service* — removes the
operational burden and adds a third-party dependency in the path of live
collaboration, plus tenant-scoping of channels becomes an application-level
guarantee.

**Trade-offs.** An extra deployable, an extra authorization path, and
subscription state that must be externalized.

**Benefits.** API deploys do not interrupt active sessions. Each tier scales on
its own signal.

**Long-term impact.** Also preserves the option to replace the gateway's runtime
independently — likely, given PHP's process model is the weakest fit for very
high connection counts (`06`).

### Why workers share the Core API codebase

**Reasoning.** Workers execute the same domain logic as HTTP handlers — approving
an artifact is the same operation whether triggered by a request or a job. A
separate codebase would duplicate the domain model, and duplicated domain models
diverge.

**Alternatives.** *Separate worker service* — independent deploy and scaling for
worker code, at the cost of either duplicating or extracting the domain into a
shared library, which is a distributed monolith with extra steps. *Workers as
threads in the API process* — no isolation; a memory-heavy export degrades
request latency.

**Trade-offs.** Workers and API deploy together, so a worker-only change
redeploys the API. Acceptable given deploy frequency and cost.

**Benefits.** One domain model, one test suite, no drift.

### Why the AI service is a separate container rather than a worker pool

**Reasoning.** Different runtime (Python), different dependency tree, different
deploy cadence (prompts change weekly), and a failure profile driven by third
parties. Every criterion in `23`'s boundary heuristics points the same way.

**Trade-offs.** A network hop, a second language, and a service contract to
maintain. All accepted in `06`.

**Benefits.** Prompt and model changes deploy without redeploying the platform.
Provider failures are contained behind a service boundary.

---

## Container Interaction Summary

| From | To | Protocol | Sync/Async | Timeout |
| --- | --- | --- | --- | --- |
| Client | Core API | HTTPS/JSON | Sync | 30 s |
| Client | Realtime | WSS | Persistent | Heartbeat 30 s |
| Core API | PostgreSQL | TCP, pooled | Sync | 5 s (statement) |
| Core API | Redis cache | TCP | Sync | 250 ms |
| Core API | Redis queue | TCP | Sync (enqueue) | 1 s |
| Core API | Object storage | HTTPS | Sync (metadata) | 10 s |
| Worker | AI service | HTTPS + SSE | Sync within job | 300 s |
| AI service | Model provider | HTTPS + streaming | Sync within workflow | 180 s |
| Core API / Worker | Redis pub/sub | TCP | Fire-and-forget | 250 ms |
| Realtime | Redis pub/sub | TCP | Subscribe | Persistent |

**Timeouts decrease inward.** A client's 30-second budget must exceed the sum of
everything it triggers synchronously; a database statement timeout must be well
under the API's request timeout. Where this hierarchy inverts, a slow dependency
produces retry storms rather than clean failures — see `34` for the full timeout
budget.

**The worker→AI 300-second timeout is deliberately long** and is precisely why
that call happens in a worker rather than a request handler (P-9).

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-307 | Three separate Redis instances: cache, queue, pub/sub | Incompatible eviction policies; cache pressure must never evict queued work |
| D-308 | Queue Redis configured `noeviction` with persistence | Evicting a job is silent, unrecoverable work loss |
| D-309 | Realtime subscription registry externalized to Redis | A deploy must not lose routing state; clients reconnect to any instance |
| D-310 | Realtime is degradable; polling fallback is a design requirement | Live updates are immediacy, never correctness |
| D-311 | Worker fleet split into four pools by workload class | One slow integration partner must not delay everyone's generation |
| D-312 | Scheduler is a separate leader-elected container | Cron inside N replicas fires N times — duplicate spend and duplicate side effects |
| D-313 | Scheduled jobs idempotent regardless of leader election | Failover produces a brief double-execution window |
| D-314 | AI service has read-only database access; workers persist results | Keeps the transactional boundary in the domain model; a malfunctioning AI service cannot corrupt the record |
| D-315 | Large objects served by short-lived signed URLs, never through the API | Keeps bulk transfer off the application tier while preserving authorization |
| D-316 | OTel Collector between applications and backend | One enforcement point for redaction, sampling and cardinality; keeps the backend replaceable |
| D-317 | Timeouts decrease strictly inward across every hop | Inverted hierarchies convert slow dependencies into retry storms |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Queue Redis fills and rejects writes | Enqueue failures surface as request errors | `noeviction` plus alerting on memory headroom; SQS migration path (`06`) |
| Realtime subscription registry becomes a bottleneck | Live updates degrade at connection scale | Registry is small and TTL-bounded; polling fallback absorbs failure |
| Worker pool misallocation starves a class | Delays concentrated in one workload | Per-pool queue age alerting; pools scale independently |
| Scheduler leader election fails, electing two leaders | Duplicate side effects | Idempotency (D-313); lock with fencing token |
| AI service read-only access is widened for convenience | Transactional boundary erodes | Enforced by database role grants, not by code review |
| Container count exceeds operational capacity | Complexity budget breached (`23`) | Every container justified by a distinct scaling or failure profile; consolidation has equal standing (D-226) |

## Dependencies

- **Depends on:** architecture (`05`), technology (`06`), scalability (`08`),
  system context (`29`).
- **Depended on by:** component architecture (`31`), communication (`34`),
  caching and queueing (`37`), resilience (`41`).

## Future Improvements

- Evaluate a dedicated runtime for the Realtime Gateway if connections approach
  the PHP process model's practical limits.
- Migrate the queue to a managed service when durability guarantees or volume
  justify it — the interface is already abstracted.
- Add per-container failure injection scenarios to validate the impact column
  rather than assuming it.
