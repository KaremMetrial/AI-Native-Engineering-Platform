# High-Level Architecture

## Purpose

Define the system's structural decomposition, its runtime components, how
requests and work flow through it, and the boundaries that must hold. This is
the architectural centre of the foundation; technology choices
(`06-technology-decisions.md`) implement it, they do not define it.

## Scope

**In scope:** architectural style, runtime components, internal layering,
communication patterns, data flow, extraction seams, and the cross-cutting
concerns that shape all of them.

**Out of scope:** specific frameworks and vendors (see `06`), infrastructure
topology (see `docs/delivery/15-deployment-strategy.md`), and detailed data
modelling (an implementation deliverable).

---

## The Central Decision: Modular Monolith + Extracted AI Service

The platform is built as a **modular monolith** for the transactional core,
with **AI orchestration as a separate service** from day one, and asynchronous
workers for all long-running work.

### Why not microservices

Microservices are the reflexive answer to "millions of users" and would be the
wrong one here.

| Consideration | Reality for this platform |
| --- | --- |
| **Domain stability** | The boundaries in `03` are *hypotheses*. Some will be wrong — Estimation and Planning may well merge. Moving a boundary inside a monolith is a refactor; moving it across services is a distributed migration with data and API versioning. |
| **Transactional integrity** | Approving a requirement updates the artifact, writes graph edges, emits events, and records audit — one transaction. Across services this becomes a saga with compensating actions and eventual-consistency bugs, to solve a problem we do not have. |
| **Team size** | Microservices pay off when independent teams need independent deploys. With one team, they add coordination cost and buy nothing. |
| **Operational cost** | Service mesh, distributed tracing across dozens of hops, per-service pipelines and on-call — an ops burden that competes directly with product delivery. |
| **Debuggability** | A cross-context trace in a monolith is a stack trace. Across services it is a correlation-ID investigation across multiple log stores. |

**Trade-offs accepted:** the whole core scales as a unit (mitigated by
statelessness and by the fact that our load is dominated by async work, not
sync throughput); a bad deploy affects everything (mitigated by trunk-based
development, small changes, feature flags, and fast rollback); and boundaries
can erode (the serious risk, mitigated by mechanical enforcement — see below).

**Migration path:** because modules own their data and communicate only through
published interfaces and events, extracting one is a bounded exercise — replace
in-process calls with network calls, split the schema along an existing seam.
We get microservices' primary benefit (the option to extract) without paying
its cost before we need it.

### Why AI orchestration *is* extracted immediately

It is the one component whose profile genuinely differs on every axis that
justifies a separate service:

- **Runtime:** the mature agent, evaluation and retrieval ecosystem is Python.
- **Execution shape:** long-running (seconds to minutes), IO-bound, high
  concurrency per instance — the opposite of the transactional core.
- **Scaling:** driven by inference concurrency and provider rate limits, wholly
  unrelated to CRUD traffic.
- **Failure profile:** depends on third-party providers with independent
  outages. NFR A-5 requires that these failures cannot degrade the core.
- **Change cadence:** prompts, models and agent graphs change far more often
  than domain logic, and must be deployable without redeploying the platform.

This is a boundary drawn on **real, present differences**, not on speculation.
That is the standard for extracting a service — and no other component meets it
today.

### Alternatives considered

| Option | Why rejected |
| --- | --- |
| Single monolith, no internal modules | Fastest initially; becomes unmaintainable by Phase 3 and forecloses extraction entirely |
| Full microservices from day one | Distributed-systems cost paid before product-market fit; boundaries locked in while still hypotheses |
| Serverless functions throughout | Cold starts conflict with P-1/P-2; unsuited to long-running AI work; local development and testing degrade sharply |
| Event-sourced core | Genuinely attractive for an audit- and version-heavy domain, but a steep learning curve and high accidental complexity for a young team. **Partially adopted instead:** artifacts are immutably versioned and domain events are first-class, without full event sourcing. |

---

## Runtime Components

```
                        Internet
                           │
                    ┌──────▼──────┐
                    │  CDN / WAF  │  static assets, TLS, DDoS, bot control
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │Load Balancer│
                    └──────┬──────┘
              ┌────────────┼────────────┐
              │            │            │
        ┌─────▼─────┐ ┌────▼─────┐ ┌────▼──────┐
        │  Web App  │ │ Core API │ │ Realtime  │
        │  (SPA)    │ │(stateless│ │  Gateway  │
        │           │ │ modular  │ │(WebSocket)│
        └───────────┘ │ monolith)│ └────┬──────┘
                      └────┬─────┘      │
                           │            │
        ┌──────────────────┼────────────┼──────────────┐
        │                  │            │              │
   ┌────▼─────┐      ┌─────▼──────┐ ┌───▼────┐  ┌──────▼──────┐
   │PostgreSQL│      │   Redis    │ │ Object │  │  Message    │
   │(primary +│      │cache/locks │ │Storage │  │   Queue     │
   │ replicas)│      │ rate limit │ │(S3)    │  │             │
   └────┬─────┘      └────────────┘ └────────┘  └──────┬──────┘
        │                                              │
        │                              ┌───────────────┴────────┐
        │                              │                        │
        │                        ┌─────▼──────┐        ┌────────▼────────┐
        └────────────────────────│  Workers   │        │ AI Orchestration│
                 (own connection)│ (async job │◀──────▶│    Service      │
                                 │ processing)│        │   (Python)      │
                                 └────────────┘        └────────┬────────┘
                                                                │
                                                       ┌────────▼────────┐
                                                       │  Model Providers│
                                                       │  (external)     │
                                                       └─────────────────┘
```

### Component responsibilities

| Component | Responsibility | Scaling driver | Notes |
| --- | --- | --- | --- |
| **CDN / WAF** | Static delivery, TLS termination, DDoS and bot mitigation, edge caching | Traffic | First line of defence; keeps volumetric attacks off origin |
| **Web App (SPA)** | All user interaction; optimistic UI; streaming result display | Served from CDN, effectively free | Thin; holds no business rules |
| **Core API** | Domain logic, authorization, transactional writes, graph operations | Concurrent requests | Stateless; the modular monolith |
| **Realtime Gateway** | Push of job progress, streamed AI output, collaborative presence | Concurrent connections | Separated from Core API because long-lived connections have different scaling and deployment characteristics than short requests |
| **Workers** | All async work: AI job coordination, exports, integration sync, notifications | Queue depth | Same codebase as Core API, different entrypoint — shares the domain model without duplicating it |
| **PostgreSQL** | System of record: artifacts, graph, tenancy, audit | Data volume, write throughput | Primary + read replicas; tenant isolation enforced here |
| **Redis** | Cache, distributed locks, rate limiting, ephemeral state | Memory, ops/sec | Never a system of record |
| **Object Storage** | Uploaded documents, generated exports, large artifacts | Volume | Cheap, durable; keeps large blobs out of the database |
| **Message Queue** | Durable async work handoff | Message volume | Enables P-9; the seam that decouples sync from slow |
| **AI Orchestration** | Agent workflows, prompt registry, retrieval, model routing, evaluation, guardrails | Inference concurrency | Separate service, separate deploy cadence |

**Why the Realtime Gateway is separate from the Core API:** WebSocket
connections are long-lived and stateful in a way HTTP requests are not. Sharing
a process means a routine API deploy drops every open connection, and
connection count — not request rate — becomes the scaling constraint for the
whole API tier. Separating them costs one more deployable unit and buys
independent scaling and deploys that do not interrupt active sessions.

---

## Internal Structure of the Core API

Each bounded context from `03` is a module with the same internal layering.
Uniformity here is deliberate: an engineer who understands one module can
navigate any of them.

```
Module (e.g. Requirements)
│
├── Domain              ← entities, value objects, domain events, domain services
│                         Pure. No framework. No IO. Fully unit-testable.
│
├── Application         ← use cases, command/query handlers, transaction boundaries,
│                         orchestration, port interfaces (outbound contracts)
│
├── Infrastructure      ← repository implementations, external adapters,
│                         persistence mapping, queue and cache adapters
│
└── Presentation        ← HTTP controllers, request validation, response mapping,
                          authorization policy attachment
```

**Dependency rule:** dependencies point inward only. Domain depends on nothing.
Application depends on Domain. Infrastructure and Presentation depend inward
and never on each other. Enforced by static analysis (M-2).

**Why Clean Architecture layering here:**

- The domain is genuinely complex — estimation models, traceability rules,
  versioning and approval semantics. This is exactly where the pattern pays.
- It makes the valuable logic testable without a database or a framework,
  which is what makes M-3 (85% domain coverage) achievable rather than
  aspirational.
- Framework churn is contained. A framework upgrade touches Infrastructure and
  Presentation; the domain is unaffected.

**Cost acknowledged:** more files and more indirection than a
framework-idiomatic CRUD app, and it is genuine overkill for simple modules. So
it is applied where it earns its cost — Requirements, Estimation, Design,
Planning, the Delivery Graph — while thin CRUD modules (feature flags, simple
lookups) use a flatter structure. **Blanket application of a pattern is as much
a failure of judgment as never applying it**; the rule is that a module's
structure matches its complexity, and reviewers check that.

### Cross-module communication

| Pattern | Use when | Mechanism |
| --- | --- | --- |
| **Published application service** | Caller needs an immediate answer within the same transaction | Direct call to another module's application layer — never its domain or repositories |
| **Domain event (in-process)** | Reaction may occur in the same transaction | Synchronous dispatch |
| **Domain event (queued)** | Reaction is independent and may be slow or fail separately | Published to queue, handled by a worker |
| **Read model** | Analytics or cross-context views | Denormalized projections built from events |

**Preference: events over direct calls.** Direct calls create compile-time
coupling and a dependency graph that must stay acyclic; events invert it, so
Requirements need not know Planning exists. Direct calls are reserved for cases
that genuinely need a synchronous answer.

---

## Key Flows

### 1. Conventional read (dominant traffic)

```
Client → CDN → LB → Core API
  → resolve tenant + actor from token
  → authorize (default deny)
  → query with tenant scope enforced at DB level
  → cache-aside via Redis where appropriate
  → response
```
Target: P-1, p95 < 300 ms.

### 2. AI generation (the defining flow)

```
Client → Core API:  POST /projects/{id}/brd:generate
  → authorize, validate, check tenant AI budget
  → create Job record (status: queued), write graph node (draft)
  → enqueue message
  → 202 Accepted + job id                          ◀── returns in < 300 ms

Worker picks up job
  → assemble context: retrieve from tenant-scoped graph + documents
  → call AI Orchestration Service (tenant id, prompt version, budget)

AI Orchestration
  → guardrails on untrusted input (SEC-9)
  → route to model by task tier
  → execute agent workflow, stream tokens back
  → validate structured output against schema
  → return result + lineage (model, prompt version, tokens, cost)

Worker
  → persist artifact version + lineage, write graph edges
  → record cost against tenant (O-5)
  → emit ArtifactGenerated
  → Realtime Gateway pushes completion to client

Client → reviews draft → approves/edits → ArtifactApproved
```

Every element of this flow traces to a requirement: 202-and-poll satisfies P-9
and A-5; budget check satisfies $-4; tenant-scoped retrieval satisfies T-7;
guardrails satisfy SEC-9; lineage satisfies U-4 and G4; the human approval step
is what makes AI output trustworthy enough to build a business on.

**The draft/approval distinction is architectural, not cosmetic.** AI produces
*drafts*; humans produce *approved artifacts*. Only approved artifacts become
authoritative inputs downstream. Without this, model error compounds through
the graph and the traceability that constitutes the moat becomes worthless.

### 3. Impact analysis (the graph's payoff)

```
Client → Core API: GET /artifacts/{id}/impact
  → traverse graph edges outward, depth-limited (P-3)
  → group affected artifacts by context and status
  → return: "changing this requirement affects 3 design docs,
             1 estimate, 12 tasks, 8 test cases"
```
Impossible without the graph, and the clearest demonstration of why it is the
core asset.

---

## Cross-Cutting Concerns

Handled once, centrally, never per-module — duplicating any of these is a
review failure.

| Concern | Approach |
| --- | --- |
| **Tenant resolution** | Middleware resolves tenant from the authenticated token and binds it to request context and DB session before any handler runs |
| **Authorization** | Declarative policies at the presentation layer; default deny; resource-scoped (D-09) |
| **Transactions** | Owned by the application layer; never opened in domain or presentation |
| **Validation** | Structural at the boundary; business invariants in the domain — two distinct responsibilities |
| **Error handling** | Domain exceptions mapped to transport errors at the edge; internal detail never leaked to clients |
| **Idempotency** | Idempotency keys on all mutating endpoints and all queue consumers — at-least-once delivery makes this mandatory, not optional |
| **Audit** | Emitted from the application layer on security- and compliance-relevant operations (SEC-5) |
| **Observability** | Tracing, structured logging and metrics via middleware; tenant ID always attached (O-3) |
| **Rate limiting** | At the edge and per tenant, enforcing S-9 fairness |

**On idempotency:** queues deliver at least once, and networks cause client
retries. A design that assumes exactly-once will produce duplicate artifacts,
double-charged AI spend, and duplicate graph edges. Making idempotency a
platform-level concern rather than a per-endpoint decision is the only reliable
approach.

---

## Extraction Seams

Pre-identified candidates, with the trigger that would justify extraction. None
is extracted now — listing them makes the modular boundaries intentional rather
than accidental.

| Candidate | Trigger for extraction |
| --- | --- |
| Analytics / reporting | Read load interferes with transactional performance |
| Integrations | Third-party sync volume or failure isolation demands it |
| Realtime gateway | Already separate |
| Estimation engine | Compute intensity justifies independent scaling |
| Billing | Compliance boundary or vendor-driven isolation |

**Discipline:** extraction requires an ADR demonstrating the trigger has been
met with evidence. "It feels too big" is not a trigger.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-29 | Modular monolith for the transactional core | Boundaries are hypotheses; refactoring beats distributed migration |
| D-30 | AI orchestration extracted from day one | Genuinely different runtime, scaling, failure and change profile |
| D-31 | Workers share the core codebase, different entrypoint | Same domain model, no duplication |
| D-32 | Realtime gateway separate from Core API | Long-lived connections scale and deploy differently |
| D-33 | Clean Architecture layering in complex modules only | Pattern applied where it earns its cost; blanket application is its own failure |
| D-34 | Events preferred over direct cross-module calls | Inverts coupling; keeps dependency graph acyclic |
| D-35 | All AI work asynchronous via queue (implements P-9) | Bounded sync latency, and provider outages cannot degrade the core |
| D-36 | AI produces drafts; only human approval makes an artifact authoritative | Prevents model error compounding through the graph |
| D-37 | Idempotency is a platform concern on all mutations and consumers | At-least-once delivery makes it mandatory |
| D-38 | Immutable artifact versioning + domain events, not full event sourcing | Most of the audit/versioning benefit at a fraction of the complexity |
| D-39 | Extraction requires an ADR with evidence the trigger was met | Prevents premature distribution |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Module boundaries erode under delivery pressure | Ball of mud; extraction becomes impossible | Mechanical CI enforcement (M-2); boundary violations block merge |
| Monolith becomes a scaling bottleneck sooner than expected | Forced extraction under time pressure | Stateless design and pre-identified seams; async absorbs most load |
| AI service becomes a distributed-systems liability | Complexity without benefit | Single clean interface; core degrades gracefully without it (A-5) |
| Event-driven flows become hard to trace | Debugging cost | Distributed tracing across event boundaries from Phase 1 (O-1) |
| Clean Architecture applied dogmatically to trivial modules | Ceremony without benefit; slower delivery | D-33 is explicit and checked in review |
| Draft/approval friction pushes users to bypass approval | Trust model collapses; G4 unmeasurable | Approval must be fast and low-friction by design, not an obstacle course |

## Dependencies

- **Depends on:** module boundaries (`03`), NFRs (`04`).
- **Depended on by:** technology decisions, multi-tenancy, scalability,
  security, AI strategy, folder strategy, deployment, testing.

## Future Improvements

- Publish the event catalogue (name, schema, producer, consumers) before the
  first cross-context event ships.
- Add C4 model diagrams (context, container, component) once the module set is
  validated by implementation.
- Re-evaluate CQRS with separate read models for Analytics when read load
  justifies it — not before.
- Reassess the monolith boundary at 10k tenants against real telemetry rather
  than intuition.
