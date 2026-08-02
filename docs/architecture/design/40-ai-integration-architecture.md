# AI Integration Architecture

## Purpose

Specify the internal architecture of AI orchestration: how a workflow executes,
how grounding context is assembled, how providers are abstracted and routed, how
output is validated, and how cost and quality are measured. `10` sets the AI
strategy; this document is its architecture.

## Scope

**In scope:** workflow execution model, context assembly pipeline, provider
abstraction, streaming, guardrails, output validation, evaluation harness, cost
metering, and the embedding pipeline.

**Out of scope:** AI strategy and policy (`10`), AI-specific security policy
(`09`), component overview (`31`).

---

## Architectural Position

The AI service is **stateless with respect to the domain, read-only with respect
to data, and authoritative for nothing.**

| Property | Consequence |
| --- | --- |
| Never invoked synchronously from a request (P-9) | Provider latency cannot affect API latency |
| Read-only database access (D-314) | Cannot corrupt the system of record |
| Returns drafts, never authoritative artifacts (D-36) | Model error cannot propagate without human review |
| Workers persist results, not the AI service | The transactional boundary stays in the domain model |
| Deliberately the most replaceable component (D-296) | Expected to be rewritten within 1–3 years |

These constraints are what make an unreliable, fast-moving, third-party-dependent
subsystem safe to build a business on.

---

## Workflow Execution Model

**Decision.** Capabilities are declarative step graphs with persisted state and
resumable execution — not imperative scripts.

```
   Workflow: brd_synthesis
   ┌──────────────────────────────────────────────────────────┐
   │ 1. validate_inputs        (deterministic)                 │
   │ 2. assemble_context       (retrieval; no inference)       │
   │ 3. guardrail_input        (deterministic)                 │
   │ 4. generate_outline       (inference · frontier tier)     │
   │ 5. generate_sections      (inference · fan-out, parallel) │
   │ 6. merge_and_validate     (deterministic; schema)         │
   │ 7. self_review            (inference · balanced tier)     │
   │ 8. emit_result + lineage  (deterministic)                 │
   └──────────────────────────────────────────────────────────┘
       state persisted after every step
```

**Reasoning.** Inference steps are slow, expensive and fail in ways that are not
deterministic. Without persisted state, a failure at step 7 discards the cost of
steps 4–6 and repeats them — real money, repeated. With persisted state,
execution resumes from the last completed step.

Declarative graphs also make workflows *inspectable*: cost per step is
attributable, the model tier per step is visible and reviewable, and evaluation
can target individual steps rather than only end-to-end output.

**Alternatives considered.**

| Alternative | Why not |
| --- | --- |
| **Imperative scripts** | Fastest to write; no resumability, no step-level cost attribution, and the model tier ends up implicit in code |
| **Agent with free tool use and open-ended looping** | Maximum flexibility; unbounded cost, unevaluable (no defined correct output), and unpredictable latency (`10`, D-89) |
| **External workflow orchestrator** | Mature and robust; another operational dependency for a workflow set that is small and internal |

**Trade-offs.** More structure than a script; adding a step means editing a
definition rather than writing a line. Fan-out steps require partial-failure
handling.

**Benefits.** Resumption without re-spending. Step-level cost and quality
attribution. Bounded execution by construction.

**Long-term impact.** As models improve, entire steps become unnecessary —
`self_review` exists to compensate for a present limitation. A declarative graph
makes deleting a step trivial; an imperative script makes it archaeology. This is
the design accommodating its own expected obsolescence (D-296).

**Bounds enforced by the engine:** maximum steps, maximum fan-out width, maximum
total tokens, maximum wall-clock, maximum retries per step, and no cycles. An
agent that can call itself is a denial-of-wallet vector, so the engine forbids
cycles structurally rather than relying on a step limit.

---

## Context Assembly Pipeline

The component that most determines output quality (`31`), specified.

```
   INPUT: artifact id · workflow · tenant · token budget
        │
   ┌────▼──────────────────────────────────────────────┐
   │ 1 · STRUCTURAL RETRIEVAL                           │
   │     graph traversal from the target artifact       │
   │     depth-bounded · APPROVED VERSIONS ONLY (D-91)  │
   │     → the artifacts this one derives from          │
   └────┬───────────────────────────────────────────────┘
   ┌────▼──────────────────────────────────────────────┐
   │ 2 · SEMANTIC RETRIEVAL                             │
   │     vector search over tenant content              │
   │     TENANT FILTER APPLIED BEFORE RANKING (D-23)    │
   │     embedding-model filter applied (D-394)         │
   └────┬───────────────────────────────────────────────┘
   ┌────▼──────────────────────────────────────────────┐
   │ 3 · RANKING                                        │
   │     structural weighted ABOVE semantic (D-90)      │
   │     recency and approval status as signals         │
   └────┬───────────────────────────────────────────────┘
   ┌────▼──────────────────────────────────────────────┐
   │ 4 · BUDGETING                                      │
   │     trim to budget · precision over recall (D-92)  │
   │     drop lowest-ranked first                       │
   └────┬───────────────────────────────────────────────┘
   ┌────▼──────────────────────────────────────────────┐
   │ 5 · ASSEMBLY  — ordering is a cost decision        │
   │     [stable prefix]  tenant conventions,           │
   │                      templates, standards          │
   │     [semi-stable]    project context               │
   │     [volatile]       the specific artifacts        │
   │     [untrusted]      delimited, labelled (SEC-9)   │
   └────┬───────────────────────────────────────────────┘
        ▼
   assembled context + retrieval manifest (→ lineage)
```

**Three properties of this pipeline are load-bearing:**

**Structural before semantic (steps 1–3).** Knowing this SRS derives from *these
three approved requirements* is exact; five semantically similar paragraphs are
approximate. Graph traversal is the platform's differentiator applied to its own
AI quality — and it is why grounding here is better than a competitor with the
same model and no graph.

**Tenant filter before ranking (step 2).** A security control, not an
optimization. Post-filtering means the ranker has already seen other tenants'
content, and any bug in the post-filter becomes a cross-tenant leak.

**Stable-prefix-first assembly (step 5).** Provider prompt caching keys on
identical prefixes. Tenant conventions and templates are identical across many
calls; placing them first means the large stable portion is cached. Reversing the
order forfeits the entire saving — a significant recurring cost turned into a
small one purely by ordering (D-100).

**The retrieval manifest** — what was retrieved, from where, at what rank — is
recorded in lineage. This is what makes a generated artifact explicable years
later (U-4), and what allows a disputed artifact to be defended.

---

## Provider Abstraction and Routing

**Decision.** A thin capability-based interface, with the Provider Router as the
only component permitted to import a provider SDK (D-323).

```
   Workflow step (declares: tier, capabilities, budget)
        │
   ┌────▼──────────────────────────────────────────┐
   │            PROVIDER ROUTER                     │
   │  ┌──────────────────────────────────────────┐  │
   │  │ Tier selection    fast / balanced /       │  │
   │  │                   frontier  (D-94)        │  │
   │  ├──────────────────────────────────────────┤  │
   │  │ Model resolution  PINNED ids, never       │  │
   │  │                   "latest"  (D-279)       │  │
   │  ├──────────────────────────────────────────┤  │
   │  │ Rate limiting     token bucket per        │  │
   │  │                   provider  (D-410)       │  │
   │  ├──────────────────────────────────────────┤  │
   │  │ Fallback          alternate provider on   │  │
   │  │                   failure/saturation      │  │
   │  ├──────────────────────────────────────────┤  │
   │  │ Cost accounting   tokens · price · tenant │  │
   │  └──────────────────────────────────────────┘  │
   └────┬───────────────────────────────────────────┘
        ▼
   Provider adapters (Anthropic primary, alternates)
```

**The abstraction is capability-based, not lowest-common-denominator.** A step
declares what it needs — structured output, long context, streaming, tool use —
and the router selects a model satisfying it. Where a provider offers something
genuinely differentiating, an explicit escape hatch is available rather than
pretending providers are interchangeable (`06`).

**Model identifiers are pinned** (D-279). An alias tracking "latest" silently
changes behaviour, invalidating evaluation baselines and destroying
reproducibility. A model change is a deliberate configuration change gated by the
evaluation suite (D-280).

**Fallback is evaluated, not hypothetical.** A fallback provider that has never
been quality-tested for a workflow is not a fallback; it is an untested
behaviour change that activates during an incident. Fallback paths run against
the golden set like any other configuration.

---

## Streaming Architecture

```
   Provider ──SSE──▶ AI service ──SSE──▶ Worker ──pub/sub──▶ Realtime ──WSS──▶ Client
                          │
                     accumulates full response
                     for validation before
                     the result is emitted
```

**Streamed tokens are display-only until validation completes.** The client
renders progressively, but the artifact is not created until the full response
passes schema validation (D-95). A stream that fails validation at the end
produces a clean failure, not a partial artifact — the user has seen text that
is then discarded, which is honest, whereas persisting a half-valid artifact is
silent corruption.

This satisfies P-6 (first token under 3s) and the perceived-performance
requirement (`26`) without compromising correctness — the two goals are met at
different layers rather than traded against each other.

---

## Guardrail Pipeline

Ordered stages; each may reject.

| Stage | Applies to | Action on failure |
| --- | --- | --- |
| Input size and structure | All input | Reject before any cost |
| Untrusted content isolation | Client-supplied content | Delimit and label; never concatenate into instructions |
| Injection heuristics | Untrusted content | Flag; constrain tool access further |
| PII redaction | Content leaving the perimeter | Redact where the use case permits |
| Budget check | Every call | Reject before dispatch, never after ($-4) |
| Output schema validation | All output | Retry with feedback → escalate tier → clean failure |
| Content safety | Client-facing artifacts | Reject and flag |
| Cost recording | Every call | Always, including failures |

**Budget checked before dispatch, not after.** Checking after the call means the
cost is already incurred — the check documents an overspend rather than
preventing it.

**Cost recorded even on failure.** A failed generation still consumed tokens.
Omitting failures from metering understates cost and breaks reconciliation
against provider invoices (O-5).

---

## Evaluation Harness

Architecture of the mechanism that gates AI quality (`10`).

```
   Trigger: prompt / workflow / model / retrieval change · or nightly
        │
   ┌────▼─────────────────────────────────────────────┐
   │ Golden set: curated inputs + reference outputs    │
   └────┬─────────────────────────────────────────────┘
   ┌────▼──────────┬──────────────┬───────────────────┐
   │ Deterministic │ LLM-as-judge │ Adversarial       │
   │ · schema      │ · complete-  │ · injection corpus│
   │ · required    │   ness       │ · HARD GATE       │
   │   sections    │ · grounded-  │                   │
   │ · links       │   ness       │                   │
   │   resolve     │ · no fabri-  │                   │
   │ · HARD GATE   │   cation     │                   │
   │               │ · THRESHOLD  │                   │
   └────┬──────────┴──────┬───────┴─────────┬─────────┘
        └─────────────────▼─────────────────┘
                  Aggregate scoring
                  vs. recorded baseline
                          │
              regression beyond tolerance → BLOCK
              improvement → new baseline recorded
```

**Deterministic layers gate hard; statistical layers gate on aggregate
regression** (D-96). Per-run pass/fail on generated prose is flaky by
construction, and flaky gates get ignored — which would leave AI quality
ungated entirely.

**Golden sets grow from production failures** (D-98): every quality incident adds
a case, so the suite hardens against exactly the failures observed rather than
the failures imagined.

**Evaluation runs on AI-affecting changes and nightly, not per PR** (D-143) — it
is slow and costs real inference money.

---

## Cost Metering

```
   Every provider call
        │
        ├── tokens in / out, model, latency
        ├── attributed: tenant · project · workflow · step · actor
        │
   ┌────▼──────────────┐
   │ Cost record       │ → PostgreSQL (aggregated per tenant, O-5)
   └────┬──────────────┘   NOT a metric label (D-417)
        │
        ├──▶ real-time tenant budget check (next call)
        ├──▶ cost per artifact type trend ($-2)
        └──▶ monthly reconciliation vs provider invoice
```

**Cost is recorded in the database, not as metrics**, because attribution must be
exact and per-tenant cardinality is unacceptable in a metrics backend (D-419).
The database is the correct store for something that is both high-cardinality and
financially authoritative.

**Monthly reconciliation against the provider invoice** is what keeps the
internal figure honest — an unreconciled estimate drifts, and margin decisions
made on a drifted figure are wrong in the direction nobody notices.

---

## Embedding Pipeline

```
   ArtifactVersionCreated (approved only)
        │
   ┌────▼───────────────────────────────────┐
   │ Chunking — semantic boundaries,         │
   │ not fixed size                          │
   └────┬───────────────────────────────────┘
   ┌────▼───────────────────────────────────┐
   │ Embed (fast tier, batched)              │
   └────┬───────────────────────────────────┘
   ┌────▼───────────────────────────────────┐
   │ Store: vector + tenantId + artifact ref │
   │        + EMBEDDING MODEL ID (D-392)     │
   └─────────────────────────────────────────┘
```

**Only approved versions are embedded** (D-91). Embedding drafts would surface
unapproved content as authoritative grounding — compounding model error through
the graph, which is the failure the draft/approval distinction exists to prevent.

**Chunking on semantic boundaries** — sections, requirements — rather than fixed
token windows. Fixed-size chunks split a requirement across two vectors, so
neither retrieves well and both are partially meaningless.

**Re-embedding on model change is a planned migration** (D-393), never a
side effect of a deploy.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-436 | Workflows are declarative step graphs with persisted state | Failure at a late step must not re-spend earlier inference |
| D-437 | Workflow engine forbids cycles structurally | Self-calling agents are a denial-of-wallet vector |
| D-438 | Step-level cost and tier attribution | Makes routing decisions reviewable and evaluable per step |
| D-439 | Provider abstraction is capability-based with explicit escape hatches | Avoids lowest-common-denominator while keeping providers replaceable |
| D-440 | Fallback providers are quality-evaluated, not merely configured | An unevaluated fallback is an untested behaviour change that fires during an incident |
| D-441 | Streamed tokens are display-only until validation completes | A partial artifact is silent corruption; discarded text is honest |
| D-442 | Budget checked before dispatch, never after | An after-the-fact check documents overspend rather than preventing it |
| D-443 | Cost recorded on failed calls too | Failed generations consume tokens; omitting them breaks reconciliation |
| D-444 | Cost stored in the database, not as metrics | Exact attribution at tenant cardinality; metrics cannot carry it |
| D-445 | Monthly reconciliation against provider invoices | Unreconciled internal estimates drift and mislead margin decisions |
| D-446 | Only approved artifact versions are embedded | Embedding drafts compounds model error through the graph |
| D-447 | Chunking follows semantic boundaries, not fixed size | Fixed windows split requirements across vectors, degrading both |
| D-448 | Retrieval manifest recorded in lineage | What makes a generated artifact explicable and defensible years later |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Workflow state grows unbounded | Storage cost; slow resumption | Retention on completed workflows; partitioned by time (`36`) |
| Context assembly quality below the usefulness threshold (CR-2) | G4 fails; the product has negative value | Retrieval quality measured in evaluation; structural-first grounding |
| Provider abstraction drifts to lowest common denominator | Capability lost to portability | Capability-based interface with escape hatches; reviewed on provider changes |
| Pinned model retired by the provider | Forced unplanned migration | Deprecation notices tracked; upgrade planned within the support window (`27`) |
| Evaluation cost grows with golden-set size | Budget pressure; temptation to skip | Tiered sets — fast subset per change, full nightly |
| Fan-out steps partially fail | Incomplete artifact | Partial-failure policy per step: retry, degrade, or fail the workflow cleanly |
| Embedding model change without planned re-embedding | Retrieval quality degrades invisibly | Model ID stored per vector; queries filter on it (D-394) |
| Prompt caching benefit lost by assembly reordering | Recurring cost increase | Assembly order asserted by test; cost per artifact monitored ($-2) |

## Dependencies

- **Depends on:** AI strategy (`10`), components (`31`), data architecture
  (`36`), security architecture (`39`).
- **Depended on by:** resilience (`41`).

## Future Improvements

- Publish workflow definitions and their evaluation rubrics together, so a
  workflow cannot ship without its quality gate.
- Add per-step cost budgets once baseline costs are measured.
- Evaluate self-hosted models for the fast tier once volume justifies GPU
  operations (`24`).
