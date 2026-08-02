# Component Architecture (C4 Level 3)

## Purpose

Decompose the significant containers into their internal components: what each
does, what it depends on, and where the cross-cutting concerns live. This is the
level at which the layering in `05` becomes a concrete arrangement of parts.

## Scope

**In scope:** internal components of the Core API, AI Orchestration Service,
Worker Fleet and Realtime Gateway, plus the request and job pipelines.

**Out of scope:** class-level design (an implementation deliverable), and the
domain model itself (`32`).

---

## Core API Components

```
                          HTTP Request
                               │
   ┌───────────────────────────▼────────────────────────────────┐
   │                   MIDDLEWARE PIPELINE                       │
   │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌───────────────┐  │
   │  │Correlation│▶│  Auth    │▶│  Tenant  │▶│ Rate Limiter  │  │
   │  │ + Tracing │ │ Resolver │ │ Context  │ │ (per tenant)  │  │
   │  └──────────┘ └──────────┘ └────┬─────┘ └───────┬───────┘  │
   │                                  │               │          │
   │                    ┌─────────────▼───────────────▼───────┐  │
   │                    │  DB Session Binder (sets RLS var)   │  │
   │                    └─────────────────┬───────────────────┘  │
   │                          ┌───────────▼──────────┐           │
   │                          │  Idempotency Filter  │           │
   │                          └───────────┬──────────┘           │
   └──────────────────────────────────────┼──────────────────────┘
                                          │
   ┌──────────────────────────────────────▼──────────────────────┐
   │              MODULE  (one per bounded context)               │
   │                                                              │
   │  PRESENTATION   Controller → Request Validator → Policy      │
   │       │                                            │         │
   │       ▼                              ┌─────────────▼──────┐  │
   │  APPLICATION    Command/Query Handler│ Authorization      │  │
   │       │         Transaction Manager  │ Engine (shared)    │  │
   │       │         Outbound Ports       └────────────────────┘  │
   │       ▼                                                      │
   │  DOMAIN         Aggregates · Value Objects · Domain Services │
   │       │         Domain Events · Repository Interfaces        │
   │       ▼                                                      │
   │  INFRASTRUCTURE Repository Impl · Event Dispatcher           │
   │                 Outbox Writer · Adapters                     │
   └──────────────────────────────────────┬──────────────────────┘
                                          │
   ┌──────────────────────────────────────▼──────────────────────┐
   │                    SHARED KERNEL COMPONENTS                  │
   │  Delivery Graph · Tenancy · Audit Writer · Job Enqueuer      │
   │  Cache Manager · Telemetry Emitter · Error Mapper            │
   └──────────────────────────────────────────────────────────────┘
```

### Middleware pipeline — order is load-bearing

**Decision.** The pipeline order is fixed and enforced: correlation → auth →
tenant context → rate limit → DB session binding → idempotency.

**Reasoning.** Each stage depends on the previous one having succeeded, and the
ordering encodes security properties that are invisible if the order is treated
as arbitrary:

| Stage | Why here |
| --- | --- |
| **Correlation + tracing** | First, so that even a rejected request is traceable. A 401 with no trace is an incident nobody can investigate. |
| **Auth resolver** | Before anything that could touch data. Establishes the actor. |
| **Tenant context** | Immediately after auth, from token claims only (D-56). **Everything downstream assumes it exists.** |
| **Rate limiter** | After tenant resolution, because limits are per tenant (S-9). Before handler work, so throttled requests cost nothing. |
| **DB session binder** | Sets the RLS session variable on the pooled connection. Must run before any query and reset on release. |
| **Idempotency filter** | Last, because it needs actor and tenant to scope the key |

**Alternatives.** *Rate limit at the edge only* — cheaper and cannot express
per-tenant fairness because the edge does not know the tenant. We do both: coarse
at the edge, per-tenant here. *Bind the tenant at the repository layer* — misses
raw queries and any code path that bypasses repositories, which is exactly the
gap RLS exists to close.

**Trade-offs.** A fixed pipeline is less flexible; adding a stage means deciding
where it belongs rather than appending it.

**Benefits.** Tenant context and RLS binding are guaranteed for every request by
construction — no handler can execute without them.

**Long-term impact.** This ordering is a security invariant. It is verified by an
architecture test so that a future refactor cannot silently reorder it.

### Cross-cutting components

Implemented once, used by every module. Duplicating any of these is a review
failure (`05`).

| Component | Responsibility | Notes |
| --- | --- | --- |
| **Tenant Context** | Resolve, hold, propagate tenant identity | Immutable per request; fatal if absent (D-57) |
| **Authorization Engine** | Evaluate RBAC + ABAC policies | Default deny; centralized so policies are auditable as a set |
| **Idempotency Store** | Deduplicate mutations; replay stored responses | Keyed by tenant + actor + idempotency key; TTL-bounded |
| **Audit Writer** | Append security- and compliance-relevant events | Append-only; records access, never content (D-84) |
| **Cache Manager** | Tenant-prefixed keys, namespace versioning, invalidation | Key builder requires a tenant — the safe path is the only path (D-58) |
| **Job Enqueuer** | Enqueue with tenant context and trace context embedded | Context loss across the async boundary is a leak vector |
| **Outbox Writer** | Persist outbound events in the same transaction as state | The mechanism that makes events reliable (`35`) |
| **Telemetry Emitter** | Traces, metrics, structured logs, all tenant-attributed | Redacting by construction (D-79) |
| **Error Mapper** | Domain exceptions → transport responses | Never leaks internal detail (`13`) |

### Delivery Graph component

The shared kernel (`03`), deliberately small and holding no business rules.

| Sub-component | Responsibility |
| --- | --- |
| **Artifact Registry** | Identity and current-version resolution |
| **Version Store** | Immutable version persistence |
| **Link Manager** | Typed edge creation and validation |
| **Lineage Recorder** | Generation provenance — model, prompt version, inputs, cost |
| **Traversal Engine** | Depth-bounded graph queries; impact analysis (P-3) |
| **Approval Registry** | Version-bound approvals (D-278) |

**The Traversal Engine is the performance-critical component** and the one whose
assumption is unvalidated (TR-2). It is isolated as a component specifically so
that replacing its implementation — recursive CTE → materialized closure table →
derived read model — touches one place.

---

## AI Orchestration Service Components

```
                    HTTP + SSE  from Worker
                            │
                  ┌─────────▼──────────┐
                  │    API Layer       │  request validation, tenant assertion,
                  │    (FastAPI)       │  streaming response
                  └─────────┬──────────┘
                            │
                  ┌─────────▼──────────┐
                  │  Workflow Engine   │  step DAG, state, resumption,
                  │                    │  loop + recursion limits
                  └─┬────┬────┬────┬───┘
        ┌───────────┘    │    │    └────────────┐
        │                │    │                 │
 ┌──────▼──────┐ ┌───────▼──┐ │  ┌──────────────▼────────┐
 │   Context   │ │  Prompt  │ │  │   Guardrail Pipeline  │
 │  Assembler  │ │ Registry │ │  │  ┌─────────────────┐  │
 │             │ │          │ │  │  │ Input sanitize  │  │
 │ ┌─────────┐ │ └──────────┘ │  │  │ Injection defence│ │
 │ │ Graph   │ │              │  │  │ PII redaction   │  │
 │ │Traversal│ │              │  │  └─────────────────┘  │
 │ ├─────────┤ │              │  └───────────────────────┘
 │ │ Vector  │ │              │
 │ │Retrieval│ │       ┌──────▼──────────┐
 │ ├─────────┤ │       │ Provider Router │──▶ Model Providers
 │ │ Ranker  │ │       │  tier routing   │
 │ ├─────────┤ │       │  fallback       │
 │ │ Budgeter│ │       │  rate limiting  │
 │ └─────────┘ │       └──────┬──────────┘
 └─────────────┘              │
                     ┌────────▼─────────┐   ┌──────────────┐
                     │ Output Validator │   │ Cost Meter   │
                     │ (schema-bound)   │   │              │
                     └────────┬─────────┘   └──────────────┘
                              │
                     ┌────────▼─────────┐
                     │  Evaluation      │  golden sets, judges,
                     │  Harness         │  regression thresholds
                     └──────────────────┘
```

### Component responsibilities

| Component | Responsibility | Key constraint |
| --- | --- | --- |
| **API Layer** | Request validation, tenant assertion, SSE streaming | **Refuses any request without a tenant identifier** — fail closed at the boundary |
| **Workflow Engine** | Execute a step DAG; persist state; resume after failure; enforce loop and recursion limits | State persisted so a crashed workflow resumes rather than restarting and re-spending |
| **Context Assembler** | Build grounding context: traverse → retrieve → rank → budget → assemble | **Stable prefix first** for prompt caching (D-100) |
| **Prompt Registry** | Resolve versioned prompt artifacts | Version returned with the result, for lineage (D-93) |
| **Provider Router** | Tier selection, fallback, per-provider concurrency and rate limiting | Only component permitted to import a provider SDK (`13`) |
| **Guardrail Pipeline** | Untrusted-content isolation, injection defence, PII redaction, output safety | Untrusted content passed in delimited positions, never concatenated into instructions |
| **Output Validator** | Schema conformance; retry with corrective feedback; tier escalation; clean failure | **Never coerces malformed output into a partial artifact** (D-95) |
| **Cost Meter** | Token and cost accounting per tenant, project, workflow | Emitted per call, reconciled monthly |
| **Evaluation Harness** | Golden sets, deterministic assertions, LLM judges, regression gating | Runs on AI-affecting changes and nightly, not per PR (D-143) |

### The Context Assembler pipeline

The component that most determines output quality, and the one where the
platform's differentiation is realized.

```
  Workflow request (artifact id, workflow, tenant)
        │
        ▼
  1. GRAPH TRAVERSAL      ── structural context: what this derives from,
     (tenant-scoped)         approved versions only (D-91)
        │
        ▼
  2. VECTOR RETRIEVAL     ── semantic context: relevant prior work
     (tenant filter          FILTER BEFORE RANKING (D-23)
      applied first)
        │
        ▼
  3. RANKING              ── structural context weighted above semantic (D-90)
        │
        ▼
  4. BUDGETING            ── trim to token budget; precision over recall (D-92)
        │
        ▼
  5. ASSEMBLY             ── stable prefix (tenant conventions, templates,
                             standards) first, volatile content last
        │
        ▼
  Assembled context + lineage record
```

**Step 2's ordering is a security control, not an optimization.** Filtering by
tenant after ranking means the ranker has already seen other tenants' content,
and any bug in the post-filter is a cross-tenant leak. Filtering first makes the
leak structurally impossible.

**Step 5's ordering is a cost control.** Provider prompt caching keys on
identical prefixes; placing tenant conventions and templates first means the
large stable portion is cached across many calls, while the small volatile
portion changes. Getting this ordering backwards forfeits the entire saving.

---

## Worker Components

```
   Queue ──▶ ┌──────────────┐
             │ Job Consumer │  lease, visibility timeout
             └──────┬───────┘
                    ▼
             ┌──────────────────┐
             │ Context Restorer │  tenant + trace context from payload
             └──────┬───────────┘  FAILS CLOSED if absent (D-57)
                    ▼
             ┌──────────────────┐
             │ Idempotency Guard│  has this job already completed?
             └──────┬───────────┘
                    ▼
             ┌──────────────────┐
             │ Handler Registry │  dispatch to typed handler
             └──────┬───────────┘
                    ▼
             ┌──────────────────┐     ┌──────────────────┐
             │  Job Handler     │────▶│ Progress Reporter│──▶ Redis pub/sub
             └──────┬───────────┘     └──────────────────┘
                    │
          ┌─────────┴─────────┐
          ▼                   ▼
   ┌─────────────┐    ┌──────────────┐
   │Retry Manager│    │ Dead Letter  │  after bounded retries,
   │ backoff+jit │    │   Handler    │  with alerting
   └─────────────┘    └──────────────┘
```

**Context Restorer is the security-critical component.** A job whose tenant
context cannot be restored must fail, never proceed unscoped. This is the async
counterpart to the middleware's tenant resolution, and the place where tenant
context is most commonly lost in practice.

**Progress Reporter exists because of P-6 and the perceived-performance
requirements** (`26`): a five-minute job with no feedback reads as a hung
product. Progress is published to Redis pub/sub and pushed by the Realtime
Gateway.

---

## Realtime Gateway Components

| Component | Responsibility |
| --- | --- |
| **Connection Manager** | Lifecycle, heartbeat, graceful drain on deploy |
| **Auth Handshake** | Validate token at connect; **re-validate periodically** so a revoked session does not persist for the connection's lifetime |
| **Subscription Registry** | Tenant- and resource-scoped subscriptions, in Redis (D-309) |
| **Authorization Checker** | Verify the subscriber may see the resource — **at subscribe time and again at publish time** |
| **Fan-out Dispatcher** | Route published events to matching connections |
| **Backpressure Manager** | Bounded per-connection buffers; slow consumers are dropped, not allowed to consume unbounded memory |

**Re-checking authorization at publish time, not only at subscribe time,** is
the non-obvious requirement. A long-lived connection subscribed when the user
had access must stop receiving updates when that access is revoked — otherwise
revocation is not effective until the user reconnects, which may be never.

---

## Request and Job Pipelines

### Synchronous read

```
Client → Edge → LB → Core API
  middleware pipeline (correlation, auth, tenant, rate limit, RLS bind)
  → Controller → Policy check → Query Handler
  → Repository (tenant-scoped by construction) → PostgreSQL (RLS enforced)
  → cache-aside via Cache Manager
  → Resource mapping → Response
```
Budget: P-1, p95 < 300 ms, decomposed in `26`.

### Asynchronous AI generation

```
Core API                Worker                 AI Service
────────                ──────                 ──────────
authorize                                      
budget check ($-4)                             
create Job + draft node                        
write outbox event ──┐                         
202 Accepted         │                         
                     ▼                         
              consume, restore context         
              idempotency check                
              assemble request ───────────────▶ guardrails
                                               context assembly
                                               provider routing
              ◀──────── SSE stream ─────────── generation
              progress → pub/sub               validation
              ◀──────── result + lineage ───── cost metering
              persist version + lineage
              write graph edges
              record cost
              emit ArtifactGenerated ──▶ outbox
                                          │
                                          ▼
                              Realtime Gateway → Client
```

Every element traces to a requirement — the numbered decisions are in `05`.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-318 | Middleware pipeline order is fixed and verified by an architecture test | The ordering encodes security invariants that are invisible if treated as arbitrary |
| D-319 | Correlation and tracing run first, before authentication | A rejected request must still be traceable |
| D-320 | Rate limiting is per tenant after tenant resolution, plus coarse limiting at the edge | The edge cannot know the tenant; per-tenant fairness requires it |
| D-321 | AI service API layer refuses requests lacking a tenant identifier | Fail closed at the service boundary, not inside it |
| D-322 | Workflow state is persisted so a crashed workflow resumes | Restarting re-spends inference for work already done |
| D-323 | Provider Router is the only component permitted to import a provider SDK | Enforced by import restriction; keeps the abstraction real |
| D-324 | Context Assembler filters by tenant before ranking | Post-filtering means the ranker has already seen other tenants' data |
| D-325 | Context assembled stable-prefix-first for provider prompt caching | Reversed ordering forfeits the entire caching saving |
| D-326 | Traversal Engine isolated as a replaceable component | Its performance assumption is unvalidated (TR-2) |
| D-327 | Worker Context Restorer fails closed on missing tenant context | The most common place tenant context is lost |
| D-328 | Realtime re-checks authorization at publish time, not only at subscribe | Otherwise access revocation is ineffective until reconnect |
| D-329 | Realtime applies bounded per-connection buffers; slow consumers are dropped | Unbounded buffering lets one slow client exhaust gateway memory |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Middleware reordered during refactoring | Security invariant silently broken | Architecture test asserts the order |
| Cross-cutting component duplicated inside a module | Divergent behaviour; audit and cache gaps | Review failure per `05`; boundary tests |
| Context Assembler complexity grows into a god component | Hard to evaluate and change | Pipeline stages are separately testable and separately replaceable |
| Workflow Engine state grows unbounded | Storage cost; slow resumption | Retention policy on completed workflow state |
| Publish-time authorization check becomes a hot path | Realtime latency | Cached authorization decisions with event-driven invalidation (`37`) |
| Traversal Engine fails P-3 | Impact analysis unusable | Isolated component; three pre-considered implementations |

## Dependencies

- **Depends on:** architecture (`05`), containers (`30`), AI strategy (`10`).
- **Depended on by:** domain model (`32`), communication (`34`), events (`35`),
  AI integration (`40`).

## Future Improvements

- Publish component-level fitness functions alongside the first module so the
  boundaries are executable, not merely drawn.
- Add a component diagram per bounded context as each is designed.
- Benchmark the Traversal Engine in Phase 0 and record which implementation the
  result selects.
