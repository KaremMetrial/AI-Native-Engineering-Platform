# AI Gateway

## Purpose

Specify the single control point through which every model call passes. The
gateway is where routing, budget enforcement, caching, rate limiting, failover,
normalization, guardrails, cost accounting and audit are applied — once, in one
place, for every caller.

## Scope

**In scope:** gateway responsibilities, the request pipeline, routing algorithm,
caching, rate limiting and failover, streaming, and the gateway's own failure
behaviour.

**Out of scope:** provider adapters and the registry (`50`), prompt composition
(`52`), guardrail content (`58`).

---

## Why a Gateway

**Decision.** All model access is mediated by a single gateway component. No
workflow, agent or service calls a provider adapter directly.

**Reasoning.** Nine concerns must be applied to every model call: authentication,
tenant governance, budget, routing, rate limiting, caching, guardrails, cost
accounting and audit. Each is easy to implement and easy to *forget*, and the
consequence of forgetting differs sharply per concern — a missed cache is
wasteful, a missed budget check is a margin hole, a missed governance filter is a
contractual breach, a missed audit entry is a compliance gap.

Applying them at each call site means N implementations, N opportunities to omit
one, and no way to verify coverage. Applying them at a single mandatory boundary
makes coverage structural.

This is D-83's principle — the safe path must be the only available path —
applied to the highest-consequence call in the system.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| **Shared library, no enforced boundary** | Lighter; nothing prevents a direct adapter call, and the first deadline-pressured bypass becomes the pattern |
| **Sidecar proxy per service** | Language-agnostic and infrastructure-level; adds a network hop and a deployment unit, and cannot see application context (workflow, step, tenant intent) needed for routing and attribution |
| **Third-party gateway product** | Mature normalization and routing out of the box; inserts a dependency in the path of the core capability, adds a processor of tenant content, and surrenders routing and caching policy (D-238) |
| **In-process gateway component with a hard boundary** *(chosen)* | Full application context, no extra hop, enforcement by import restriction |

**Trade-offs.** The gateway is a chokepoint: a bug in it affects every AI
operation, and it will accumulate responsibilities. Mitigated by keeping its
stages independently testable and by treating additions to it as architecturally
significant.

**Benefits.** Every cross-cutting concern applied uniformly and verifiably. One
place to change routing policy, add a provider, or tighten governance.

**Long-term impact.** As providers churn (`23`: 1–3 years), the gateway is the
seam that absorbs the churn. Without it, provider change is a codebase-wide
migration.

**Deployment position:** a component *inside* the AI Orchestration service
(`31`), not a separate deployable. It carries no independent scaling or failure
profile (`33`'s extraction criteria), so extracting it would add a hop and a
failure domain for nothing. **Extraction trigger:** a second service outside AI
Orchestration requires direct model access.

---

## Request Pipeline

Stages run in a fixed order. As with the middleware pipeline (D-318), the order
encodes properties that are invisible if treated as arbitrary.

```
   Model call request  (workflow, step, capabilities, prompt, tenant, budget ref)
        │
   ┌────▼──────────────────────────────────────────────────────────┐
   │  1 · AUTHENTICATE          service token; tenant asserted      │
   │      reject if tenant absent — fail closed (D-321)             │
   ├───────────────────────────────────────────────────────────────┤
   │  2 · GOVERNANCE FILTER     tenant allowed-provider set (D-559) │
   │      narrows candidates BEFORE any optimization                │
   ├───────────────────────────────────────────────────────────────┤
   │  3 · BUDGET CHECK          tenant · project · workflow · call  │
   │      reject BEFORE dispatch, never after (D-442)               │
   ├───────────────────────────────────────────────────────────────┤
   │  4 · CACHE LOOKUP          exact-match, tenant-partitioned     │
   │      hit → return, record saving, skip to 10                   │
   ├───────────────────────────────────────────────────────────────┤
   │  5 · GUARDRAILS (input)    injection defence, PII, size (`58`) │
   ├───────────────────────────────────────────────────────────────┤
   │  6 · ROUTE                 capability match · health · score · │
   │      cost · load  → ordered candidate list                     │
   ├───────────────────────────────────────────────────────────────┤
   │  7 · ADMIT                 per-provider token bucket (D-410);  │
   │      wait or shed; circuit breaker consulted                   │
   ├───────────────────────────────────────────────────────────────┤
   │  8 · DISPATCH              adapter normalizes and calls;       │
   │      stream or complete; timeout enforced                      │
   ├───────────────────────────────────────────────────────────────┤
   │  9 · GUARDRAILS (output)   schema validation, safety, leakage  │
   │      invalid → retry w/ feedback → escalate tier → clean fail  │
   ├───────────────────────────────────────────────────────────────┤
   │ 10 · ACCOUNT & AUDIT       usage, cost (cached split), latency,│
   │      model, prompt version → records; audit entry              │
   └────┬──────────────────────────────────────────────────────────┘
        ▼
   Result + usage + lineage
```

**Ordering rationale for the three stages most often misplaced:**

- **Governance before routing (2 before 6).** Filtering candidates by tenant
  policy first means no optimization can select a disallowed provider. Applying
  it after routing would make the constraint a post-hoc veto — one that a bug or
  a fallback path could skip.
- **Budget before cache (3 before 4).** A cache hit costs nothing, so checking
  budget first looks wasteful. It is deliberate: a tenant over budget should
  receive a consistent, explainable failure rather than intermittent success
  determined by cache state.
- **Cache before guardrails (4 before 5).** A cached response was already
  guardrailed on the way in; re-running input guardrails on a cache hit spends
  latency to reach the same conclusion.

---

## Routing

**Decision.** Routing is a deterministic, ordered filter-then-rank over registry
candidates. It is never random and never a user choice (D-94).

```
   candidates = registry.models

   FILTER   tenant allowed-provider set          ← governance, non-negotiable
   FILTER   status == active
   FILTER   capabilities ⊇ workflow requirements
   FILTER   context_window ≥ assembled prompt size
   FILTER   circuit breaker closed
   FILTER   provider has rate-limit headroom

   RANK     by workflow pin (if set)
            then evaluation score for THIS workflow (`57`)
            then cost per expected output
            then observed latency
            then load balance across healthy providers

   → ordered candidate list; attempt in order on retryable failure
```

**Reasoning for evaluation score ahead of cost.** Cost is a first-class concern
($-1), and routing to a cheaper model that produces output failing G4's
usefulness threshold costs *more* — it produces rework, regeneration and
abandonment. Quality-per-cost is only meaningful once quality clears the bar, so
the ranking clears quality first and optimizes cost within the qualifying set.

**Workflow pinning** allows a workflow to require a specific model where
evaluation shows one is materially better and substitution would be a regression.
A pin is a declared constraint with a stated reason, reviewed like any other, and
it narrows failover — which is exactly why it must be deliberate.

**Escalation on failure** (D-95): a workflow whose output fails schema validation
may retry at a higher tier, bounded. This is routing driven by outcome rather
than by prediction, and its bound is what stops it becoming a cost hole.

**Alternatives.** *Cheapest capable model* — optimizes the wrong variable when
quality varies; produces the CR-2 failure mode. *Always frontier* — indefensible
economics for classification and extraction (D-94). *Random or round-robin across
capable models* — non-reproducible, and makes quality regressions
un-attributable.

---

## Caching

**Decision.** Exact-match, tenant-partitioned response caching keyed on the
complete input. **Semantic (approximate-match) caching is prohibited.**

**Key composition:** `tenant_id · model_id · prompt_version · rendered_prompt_hash
· parameters_hash · tool_schema_hash`

**Reasoning.** Exact-match caching is provably safe: identical inputs to a model
at a fixed temperature and seed yield the same computation, so a hit is not an
approximation. The saving is substantial — classification, extraction and
repeated generations over unchanged context recur far more often than intuition
suggests — and it saves money as well as latency (D-263).

**Semantic caching is prohibited deliberately**, and the reasoning matters
because it is a popular technique: serving a cached response for a
*similar-but-not-identical* prompt means returning an answer to a question nobody
asked. In a platform whose output becomes requirements and contracts, a
near-match substitution is a correctness defect that is invisible — the output
looks plausible and is subtly about something else. Cross-tenant semantic
matching would additionally be a leak (D-82).

**Tenant partitioning is structural**, not a filter: the tenant ID is part of the
key, so a cross-tenant hit is not expressible.

**What is not cached:** anything with a non-deterministic parameter set
(temperature above zero without a fixed seed), agent steps whose tool results
vary, and any call whose prompt contains a timestamp or nonce — the Prompt Engine
flags these as uncacheable at render time rather than the gateway guessing.

**Two-layer caching:**

| Layer | Scope | Mechanism |
| --- | --- | --- |
| **Response cache** (ours) | Whole-call results | Redis + object storage for large payloads |
| **Prompt/prefix cache** (provider) | Stable prefix within a call | Per-provider mechanism, driven by declared intent (D-556) |

These compose: a cache miss at our layer can still be a prefix hit at the
provider's, which is why deterministic rendering matters even for uncached calls.

---

## Rate Limiting, Failover and Backpressure

| Mechanism | Behaviour |
| --- | --- |
| **Token bucket per provider** | Shared across workers via Redis; acquire before dispatch, wait rather than issue a request destined for 429 (D-410) |
| **Circuit breaker per provider** | Opens on sustained failure; scoped per provider and per tenant where the failure is tenant-specific (D-450) |
| **Failover** | Next candidate in the routed list, **only if it is evaluated for this workflow** (D-440) |
| **Backpressure** | Bucket exhaustion blocks the calling worker rather than queueing inside the gateway (D-363) |
| **Load shedding** | Above a hard ceiling, reject fast with a retry signal (D-364) |

**Failover restricted to evaluated candidates is the important constraint.** An
unevaluated fallback is an untested behaviour change that activates during an
incident — precisely when nobody is watching output quality. If no evaluated
alternative exists, the correct behaviour is to queue and retry the primary, not
to substitute an unknown.

---

## Streaming

```
   Provider stream ──▶ Adapter (normalize events) ──▶ Gateway ──▶ Caller
                                                        │
                                                   accumulate full
                                                   response for
                                                   validation
```

The gateway forwards normalized stream events while accumulating the complete
response, because output guardrails and schema validation require the whole thing
(D-441). Streamed tokens are display-only until validation passes; a stream that
fails validation produces a clean failure rather than a persisted partial
artifact.

**Cancellation propagates**: a cancelled downstream job cancels the provider
request, stopping token spend. Without propagation, cancelling a generation
saves the user's time and none of our money.

---

## Cost Accounting

Every call emits a usage record (`54`) with:

- Tokens in / out, **cached vs uncached separated** (D-553)
- Model, provider, tier
- Latency, retries, escalations, cache outcome
- Attribution: tenant · project · workflow · step · prompt version · actor
- Computed cost from registry pricing

**Recorded on failures too** (D-443) — a failed generation still consumed tokens,
and omitting failures understates cost and breaks reconciliation.

---

## Gateway Failure Behaviour

| Failure | Behaviour |
| --- | --- |
| Registry unavailable | Serve from last-known-good registry snapshot; refuse unknown models |
| Budget store unavailable | **Fail closed** — reject calls. Unmetered spend is worse than unavailability ($-4) |
| Cache unavailable | **Fail open** — proceed uncached (D-359) |
| Rate-limit store unavailable | Fail closed to a conservative local limit — better to under-use capacity than to breach a provider limit |
| All providers unavailable | Clean failure; job queues and retries; platform unaffected (A-5) |
| Guardrail service error | **Fail closed** — never dispatch ungarded input |

**Two fail-closed choices are deliberate and worth stating.** Budget: an
unmetered spend path is a direct margin hole that can run for hours before anyone
notices, so unavailability is preferable. Guardrails: dispatching unguarded input
would bypass injection defence entirely, which is the one control standing
between hostile content and tool access.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-563 | All model access is mediated by the gateway; direct adapter calls are prohibited | Nine cross-cutting concerns must apply to every call; per-call-site implementation guarantees omissions |
| D-564 | Gateway is a component inside AI Orchestration, not a separate service | No independent scaling or failure profile; extraction trigger stated |
| D-565 | Pipeline stage order is fixed: governance before routing, budget before cache, cache before input guardrails | Each ordering encodes a property that is invisible if treated as arbitrary |
| D-566 | Routing filters on governance and capability, then ranks quality before cost | Cheap output below the usefulness threshold costs more through rework |
| D-567 | Workflow model pinning permitted as a declared, reviewed constraint | Some workflows genuinely regress on substitution; pinning narrows failover, so it must be deliberate |
| D-568 | Exact-match caching only; **semantic caching prohibited** | Near-match substitution answers a question nobody asked, invisibly, in artifacts that become contracts |
| D-569 | Tenant ID is structurally part of the cache key | A cross-tenant hit must not be expressible |
| D-570 | The Prompt Engine marks calls uncacheable at render time | The gateway must not guess at determinism |
| D-571 | Failover only to models evaluated for that workflow | An unevaluated fallback is an untested behaviour change firing during an incident |
| D-572 | Cancellation propagates to the provider | Otherwise cancelling saves the user's time and none of our money |
| D-573 | Budget store and guardrail failures fail closed; cache failure fails open | Unmetered spend and unguarded dispatch are worse than unavailability; cache is latency only |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Gateway becomes a monolithic chokepoint | Hard to change; bugs affect all AI operations | Stages independently testable; additions treated as architecturally significant |
| A caller bypasses the gateway | Governance, budget, audit and caching all skipped | Import restriction enforced by static analysis (D-551) |
| Cache key omits an input dimension | Wrong response served as a hit | Key composition asserted by test; every parameter affecting output is in the key |
| Routing ranks stale evaluation scores | Quality regression from a bad route | Scores carry recency; stale scores demote a candidate rather than being trusted |
| Governance filter bypassed by a fallback path | Contractual breach | Filter applied once, before candidate list construction; per-tenant provider usage audited |
| Budget fail-closed causes an outage during a store incident | AI unavailable | Budget store is Redis-backed with a conservative local fallback; alerting on fail-closed activation |
| Token bucket state loss permits a burst | Provider limit breach | Conservative refill on restart; provider 429 handling as backstop |

## Dependencies

- **Depends on:** multi-provider foundation (`50`), AI integration (`40`),
  caching and queueing (`37`), security architecture (`39`).
- **Depended on by:** prompt engine (`52`), PromptOps (`53`, `54`), workflows and
  agents (`56`), evaluation (`57`), AI security (`58`).

## Future Improvements

- Publish the gateway's stage contract so each stage can be tested and replaced
  independently.
- Add adaptive routing that incorporates recent observed quality signals (`54`)
  alongside offline evaluation scores.
- Evaluate extraction to a standalone service if a second consumer outside AI
  Orchestration requires model access.
