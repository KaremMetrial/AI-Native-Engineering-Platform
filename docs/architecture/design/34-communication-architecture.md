# Communication Architecture

## Purpose

Define how components talk to each other: protocols, synchronous and
asynchronous patterns, contracts, timeout and retry budgets, idempotency, and
the coordination of multi-step processes. Communication decisions determine
failure behaviour more than any other category — most outages are a failure of
one component to handle another's failure well.

## Scope

**In scope:** communication styles and when each applies, protocol choices,
contract management, timeout and retry budgets, idempotency architecture,
backpressure, and process coordination.

**Out of scope:** event semantics and delivery (`35`), queue topology (`37`),
resilience patterns in depth (`41`).

---

## Communication Styles

**Decision.** Four styles, each with a defined trigger. The style is chosen by
the nature of the interaction, not by convenience.

| Style | Use when | Mechanism |
| --- | --- | --- |
| **Synchronous request/response** | The caller cannot proceed without the answer, and the answer is fast and bounded | HTTP/JSON, in-process method call |
| **Asynchronous command** | Work must happen, the caller need not wait, and exactly one consumer should act | Queue message |
| **Asynchronous event** | Something happened; zero or more consumers may care | Event published via outbox (`35`) |
| **Streaming** | A result is produced incrementally and partial output is useful | SSE (server→server), WebSocket (server→client) |

**The default is asynchronous.** Synchronous coupling propagates both latency and
failure: a synchronous call means the caller is only as available and as fast as
the callee. Every synchronous hop added to a request path multiplies failure
probability and adds its latency to the budget.

**Reasoning for the default.** With eight external dependencies and four internal
containers, a synchronous-by-default architecture would have an availability
ceiling well below A-1 before any of our own bugs. Asynchrony converts a
dependency's failure from *our* failure into a delay.

**Alternatives considered.** *Synchronous by default, async for slow things* —
the conventional approach; produces creeping synchronous coupling as each
individual call seems fast enough, until the request path has nine hops.
*Everything asynchronous* — maximum decoupling, and makes simple reads absurd; a
user asking for a list of requirements needs an answer now.

**Trade-offs.** Asynchrony costs: eventual consistency users can observe,
correlation to trace a logical operation across hops, and harder debugging.
These are real and are why `38` treats trace propagation across async boundaries
as a first-class requirement.

**Benefits.** Failure isolation, independent scaling, and bounded request
latency regardless of downstream slowness.

**Long-term impact.** Communication style is difficult to change later —
converting a synchronous dependency to asynchronous means changing the caller's
model of the interaction, its error handling, and often its UI. Choosing
correctly at design time is far cheaper than converting.

---

## Protocol Decisions

### Client ↔ Core API · REST over HTTPS with JSON

**Reasoning.** Universally understood, debuggable with ordinary tools, cacheable
at the edge, and directly expressible as an OpenAPI contract from which both the
frontend types and the AI service's client are generated (D-110).

**Alternatives.** *GraphQL* — genuinely attractive for our graph-shaped domain
and flexible client queries; rejected for three concrete reasons: per-tenant rate
limiting and cost control require query complexity analysis rather than simple
request counting; authorization must be enforced per field rather than per
endpoint, which is a substantially larger surface to get right (SEC-4); and edge
caching is largely forfeited. Revisit if client query flexibility becomes a
demonstrated constraint. *gRPC* — excellent for service-to-service, poor fit for
browsers without a proxy layer. *tRPC or similar* — couples frontend and backend
to one language, foreclosing the AI service and third-party integrators.

**Trade-offs.** Over-fetching and under-fetching relative to GraphQL, mitigated
by purpose-built endpoints for the views that need them.

### Core API ↔ AI Service · HTTP with SSE streaming

**Reasoning.** A simple, debuggable contract across a language boundary. SSE for
streaming because it is one-directional (which matches the interaction), works
over ordinary HTTP infrastructure, and reconnects natively.

**Alternatives.** *gRPC with bidirectional streaming* — better typed and better
performing, and adds toolchain weight across two ecosystems for a call volume
that does not justify it. *WebSocket* — bidirectional when we only need one
direction. *Queue-mediated only* — no streaming, which forfeits P-6's
first-token target and the perceived-performance benefit that makes long
generations tolerable.

### Client ↔ Realtime Gateway · WebSocket

Bidirectional, low-latency push for progress, streamed output and presence.
**With a mandatory polling fallback** (D-310) — WebSocket connections fail behind
corporate proxies more often than teams expect, and the product must work
anyway.

### Internal cross-module · In-process method calls and events

Within the modular monolith, cross-module communication is a direct call to a
published Application-layer service or an event (D-34). **No internal HTTP.**

**Reasoning.** Internal HTTP between modules in the same process is the
"distributed monolith" anti-pattern: serialization cost, network failure modes
and versioning obligations, in exchange for a boundary that static analysis
already enforces more strictly than a network would.

---

## Timeout and Retry Budget

**Decision.** Timeouts decrease strictly inward, and the total retry budget for a
request path is bounded so that retries cannot amplify load.

**Reasoning.** This is the mechanism behind most cascading failures. When an
inner timeout exceeds an outer one, the caller gives up while the callee keeps
working — wasted capacity and no result. When each layer retries independently,
retries multiply: three layers retrying three times each produces 27 requests
against a struggling dependency, converting a slowdown into an outage.

### The budget

| Hop | Timeout | Retries | Effective worst case |
| --- | --- | --- | --- |
| Client → Edge | 30 s | 0 (user-initiated) | 30 s |
| Edge → Core API | 25 s | 0 | 25 s |
| Core API → PostgreSQL | 5 s statement | 1 on connection error only | ~10 s |
| Core API → Redis cache | 250 ms | 0 — **fail open to source** | 250 ms |
| Core API → Redis queue | 1 s | 2 | ~3 s |
| Core API → Object storage | 10 s | 2 | ~30 s |
| Worker → AI service | 300 s | 1 | ~600 s (bounded by job timeout) |
| AI service → Provider | 180 s | 2 with backoff | ~540 s |

**Rules:**

1. **Retry only idempotent operations**, and only on retryable failures —
   timeouts, connection errors, 429, 503. **Never retry a validation error or a
   business-rule rejection**; those are deterministic and retrying them amplifies
   load for a guaranteed failure.
2. **Retry at one layer only** per logical operation. Deeper layers fail fast and
   report; the outermost responsible layer decides.
3. **Exponential backoff with full jitter**, always. Without jitter, retries
   synchronize and arrive as a thundering herd precisely when the dependency is
   weakest.
4. **Cache reads fail open**, going to the source. A cache outage must degrade
   latency, never availability.
5. **Every timeout is explicit.** A default or absent timeout is a defect —
   infinite waits are how thread pools exhaust.

**Trade-offs.** Single-layer retry means a transient inner failure surfaces
further out than it strictly needed to. Accepted: predictable failure behaviour
is worth more than squeezing out marginal recoveries.

---

## Idempotency Architecture

**Decision.** Every mutating API endpoint accepts an idempotency key; every queue
consumer is idempotent by construction.

**Reasoning.** At-least-once delivery makes duplicate processing certain, not
possible. Network retries make duplicate requests certain. Without idempotency
the observable results are duplicate artifacts, double-charged AI spend,
duplicate graph edges and duplicate notifications — and every one of those is a
data-integrity defect that is difficult to detect and awkward to repair.

### Mechanism

```
  Request with Idempotency-Key
        │
        ▼
  Key = hash(tenantId, actorId, endpoint, clientKey)
        │
        ▼
  ┌─────────────────────────────────┐
  │ Reserve key (atomic insert)     │
  └────┬──────────────────┬─────────┘
       │ new              │ exists
       ▼                  ▼
   execute          ┌──────────────┐
   store response   │ completed?   │
   return           │  yes → replay stored response
                    │  in-flight → 409 Conflict, retry later
                    └──────────────┘
```

**Key properties:**

- Keys are **tenant- and actor-scoped**, so one tenant's key cannot collide with
  another's.
- The **response is stored**, so a retry returns the original result rather than
  a "duplicate" error — a retry should be indistinguishable from success.
- **In-flight requests return 409** rather than executing concurrently.
- Keys expire (24 hours), bounding storage.
- **The reservation and the work commit in the same transaction**, so a crash
  between them cannot leave a key reserved for work that never happened.

**Queue consumers** use the job ID as the natural idempotency key, checked
against a completed-jobs record before handling.

**Alternatives.** *Natural idempotency only* — designing every operation so
repetition is harmless; ideal where achievable and impossible for operations
that inherently create things. *Deduplicate at the queue* — helps and does not
address client-side retries at the API. *Ignore duplicates* — the common
default, and the source of the defects above.

---

## Contract Management

**Decision.** The OpenAPI specification in `packages/contracts` is the single
source of truth; the frontend client and the AI service's client are generated
from it (D-110).

**Reasoning.** Hand-maintained clients drift from the server, and drifted clients
are worse than none because they are trusted while wrong. Generation makes drift
a build failure rather than a production defect.

**Verification runs from both sides:** the API's responses are tested for
conformance to the contract, and consumers are compiled against generated types.
A breaking change fails CI rather than a customer integration.

**Internal module contracts** are not versioned — they are refactored freely,
since every consumer is updated in the same commit (D-267, D-281).

---

## Backpressure

Every asynchronous path needs a defined behaviour when the consumer cannot keep
up. Absent an explicit answer, the implicit answer is unbounded memory growth
followed by a crash.

| Path | Backpressure mechanism |
| --- | --- |
| API → queue | Per-tenant rate limits; queue depth alerting; enqueue rejection at a hard ceiling |
| Worker → AI service | Bounded concurrency per worker pool; the pool blocks rather than queueing internally |
| AI service → provider | Token-bucket per provider; requests wait rather than being issued and rejected |
| Realtime → client | Bounded per-connection buffer; slow consumers dropped (D-329) |
| Event producers → consumers | Consumer lag monitoring; scaling on lag rather than depth |

**Load shedding** applies above the ceiling: when a tenant exceeds its quota or
the platform is saturated, requests are rejected quickly with a clear retry
signal. **Fast rejection is a better failure than slow acceptance** — a queued
request that times out consumes capacity and delivers nothing.

---

## Process Coordination

Most flows are a single transaction plus asynchronous follow-up. A few span
multiple contexts over time and need explicit coordination.

**Decision.** Multi-step cross-context processes are coordinated by a **process
manager** in the owning context, driven by events, with explicit compensating
actions. We do not use distributed transactions.

**Reasoning.** Two-phase commit across contexts requires all participants
available simultaneously and holds locks across network calls — the availability
and contention profile we spent the architecture avoiding. A process manager
makes the intermediate states explicit and inspectable, which is what makes a
long-running business process debuggable.

**Example — contract execution:**

```
  ProposalAccepted
        │
        ▼
  ┌──────────────────────────┐
  │ ContractProcess (owner:  │
  │  Commercial context)     │
  └──┬───────────────────────┘
     │ 1. generate contract          → ContractGenerated
     │ 2. dispatch for signature     → SignatureRequested
     │    ⏱ awaiting external event
     │ 3. all parties signed         → ContractExecuted
     │ 4. emit for downstream        → ProjectAuthorized
     │
     ├─ timeout at step 2  → SignatureExpired  → notify, allow reissue
     └─ rejection at step 2 → ContractRejected → compensate: revert
                                                  proposal to draft
```

**Rules:**

- The process manager owns the state machine and persists its state — an
  in-memory process is lost on deploy.
- Every waiting state has a **timeout**; a process with no timeout waits forever
  and becomes an invisible stuck record.
- Compensating actions are **explicit and modelled**, not implicit rollback.
- Process state is queryable, because "where is this contract stuck?" is a
  question support will ask on day one.

**Trade-offs.** More moving parts than a transaction, and compensations are
business decisions that must be designed rather than derived. Accepted: these
processes involve human and external steps that no transaction could span
anyway.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-351 | Asynchronous is the default; synchronous requires justification | Synchronous hops multiply failure probability and add latency to the budget |
| D-352 | REST/JSON for the public API; GraphQL rejected for now | Per-field authorization and query-complexity rate limiting are materially larger surfaces |
| D-353 | SSE for Core↔AI streaming; WebSocket only client-facing | Matches the directionality; works over ordinary HTTP infrastructure |
| D-354 | No internal HTTP between modules in one process | Distributed-monolith cost for a boundary static analysis already enforces better |
| D-355 | Timeouts decrease strictly inward across every hop | Inverted hierarchies waste capacity and produce results nobody receives |
| D-356 | Retry at one layer only, per logical operation | Multi-layer retry multiplies load exactly when a dependency is weakest |
| D-357 | Retries use exponential backoff with full jitter | Unjittered retries synchronize into a thundering herd |
| D-358 | Never retry deterministic failures | Guaranteed-failure retries amplify load for nothing |
| D-359 | Cache reads fail open to the source | A cache outage must degrade latency, never availability |
| D-360 | Every timeout is explicit; absent timeouts are defects | Infinite waits exhaust connection and thread pools |
| D-361 | Idempotency keys are tenant- and actor-scoped, with stored responses | A retry must be indistinguishable from the original success |
| D-362 | Key reservation and work commit in the same transaction | Otherwise a crash leaves a key reserved for work that never happened |
| D-363 | Every asynchronous path declares a backpressure mechanism | The implicit default is unbounded memory growth then a crash |
| D-364 | Fast rejection preferred over slow acceptance under load | A queued request that times out consumes capacity and delivers nothing |
| D-365 | Process managers with explicit compensations; no distributed transactions | 2PC requires simultaneous availability and holds locks across the network |
| D-366 | Every process-manager waiting state has a timeout | A process without one becomes an invisible stuck record |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Synchronous coupling creeps in as individually-fast calls | Availability ceiling falls silently | Architecture test on the request path; synchronous additions justified in review |
| Timeout hierarchy inverted by a configuration change | Cascading failure under load | Timeouts centrally configured and asserted by test |
| Idempotency keys omitted by clients | Duplicate side effects | Keys required, not optional, on mutating endpoints; requests without one are rejected |
| Process manager state grows unbounded | Storage cost; slow queries | Retention on completed processes; stuck-process alerting |
| Backpressure untested until real load | Discovered during an incident | Load and spike tests exercise the ceilings (`14`) |
| Generated clients drift because generation is skipped | The drift the contract was meant to prevent | Generation runs in CI; a stale client fails the build |

## Dependencies

- **Depends on:** containers (`30`), components (`31`), context map (`33`).
- **Depended on by:** events (`35`), caching and queueing (`37`), resilience
  (`41`).

## Future Improvements

- Publish the full timeout and retry budget as configuration derived from one
  source, so the table and reality cannot diverge.
- Re-evaluate GraphQL for the graph-exploration surfaces specifically, where its
  advantages are strongest and the authorization surface is narrowest.
- Add process-manager state dashboards before the first multi-step process ships.
