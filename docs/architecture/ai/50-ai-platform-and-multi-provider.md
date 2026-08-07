# AI Platform Architecture and Multi-Provider Strategy

## Purpose

Define the AI platform's overall shape and its multi-provider foundation:
supporting Claude, GPT, Gemini, DeepSeek and providers not yet released, without
degrading to the lowest common denominator and without letting provider
differences leak into workflow code.

## Scope

**In scope:** the AI platform's subsystem map, the multi-provider decision, the
model registry, capability modelling, normalization, provider-specific escape
hatches, per-tenant provider governance, and the process for adding a provider.

**Out of scope:** the gateway that implements routing (`51`), prompt composition
(`52`), evaluation (`57`).

---

## Amendment to Prior Decisions

This document amends two earlier decisions. Both are extensions rather than
reversals, and both are recorded here in the form `28`'s reversibility ledger
expects.

| Prior | Was | Now | Why |
| --- | --- | --- | --- |
| **D-51** | Claude as default, behind an abstraction that *could* support others | **Genuine multi-provider**: Claude, GPT, Gemini, DeepSeek and future models are all first-class, selected per workflow by evaluated evidence | An abstraction that has never carried a second provider does not work. Concentration risk on the platform's core capability is unacceptable, and per-task capability differences are now large enough to exploit. |
| **D-89** | Capabilities are defined workflows, never open-ended agents | Workflows remain the unit of capability; **agents are added as a bounded step type** within a workflow (`56`) | Agent reasoning is genuinely useful for exploratory steps. The original objection — unevaluable, unbounded, unauthorized — is addressed by bounding rather than by prohibition. |

Each requires an ADR before implementation. The reasoning is stated in full in
this document and in `56` respectively.

---

## AI Platform Subsystem Map

```
                        Workflow / Agent invocation
                                    │
   ┌────────────────────────────────▼─────────────────────────────────┐
   │                        AI  GATEWAY  (51)                          │
   │   auth · budget · routing · rate limit · cache · failover ·       │
   │   normalization · guardrails · cost accounting · audit            │
   └───┬──────────┬──────────┬──────────┬──────────┬──────────┬───────┘
       │          │          │          │          │          │
   ┌───▼────┐ ┌───▼────┐ ┌───▼────┐ ┌───▼────┐ ┌───▼────┐ ┌──▼──────┐
   │ Prompt │ │Context │ │ Memory │ │Knowledge│ │Workflow│ │Evaluation│
   │ Engine │ │ Engine │ │ Engine │ │ Engine │ │+ Agents│ │  System  │
   │  (52)  │ │  (55)  │ │  (55)  │ │  (55)  │ │  (56)  │ │   (57)   │
   └───┬────┘ └────────┘ └────────┘ └────────┘ └────────┘ └──────────┘
       │
   ┌───▼──────────┐
   │Prompt Library│  (52)   ── governed by PromptOps (53, 54)
   └──────────────┘
                                    │
   ┌────────────────────────────────▼─────────────────────────────────┐
   │                       MODEL REGISTRY                              │
   │   pinned model ids · capabilities · pricing · tier · status ·     │
   │   evaluation scores per workflow · data-governance attributes     │
   └───┬──────────┬──────────┬──────────┬─────────────────────────────┘
       │          │          │          │
   ┌───▼───┐  ┌───▼───┐  ┌───▼────┐ ┌───▼──────┐   ┌──────────────┐
   │Anthropic│ │OpenAI │  │ Google │ │ DeepSeek │ … │ future / self│
   │ Claude │  │ GPT   │  │ Gemini │ │          │   │   hosted     │
   └────────┘  └───────┘  └────────┘ └──────────┘   └──────────────┘
```

**Every model call passes through the gateway. No exceptions**, enforced by
import restriction (D-323 extended): provider SDKs are importable only by
provider adapters, and adapters are reachable only through the gateway.

---

## The Multi-Provider Decision

**Decision.** Support Anthropic Claude, OpenAI GPT, Google Gemini, DeepSeek and
future providers as first-class options, with per-workflow selection driven by
evaluated evidence and per-tenant governance constraints.

**Reasoning.**

1. **Concentration risk on the core capability.** A single provider's outage,
   price change, capability regression, terms change or capacity constraint would
   directly degrade the product's differentiator. `19` TR-4 rates this Medium
   likelihood; single-provider makes its impact High rather than Medium.
2. **Capability differences are now material per task.** Long-context synthesis,
   structured output fidelity, reasoning depth, latency and price per token
   differ enough between frontier models that the best model for BRD synthesis is
   not necessarily the best for classification or for code-adjacent reasoning.
   Selecting per workflow, on evidence, is worth real money and real quality.
3. **Price leverage and cost structure.** Cost is an architectural property here
   ($-1 to $-4). Providers differ by an order of magnitude at comparable quality
   for some task classes; being able to route on measured quality-per-cost is a
   direct margin lever.
4. **Tenant governance requirements.** Enterprise customers impose constraints on
   which processors may see their data and where. A single-provider architecture
   cannot satisfy a tenant who excludes that provider — and the request will
   arrive (`19` BR-5).
5. **Capacity and rate limits.** Provider-side limits are a real throughput
   ceiling (D-68). Multiple providers multiply available capacity.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| **Single provider** | Simplest; best possible use of one provider's specific features; unacceptable concentration risk on the differentiator, no price leverage, and cannot satisfy provider-constrained tenants |
| **Two providers, primary and fallback** | Most of the resilience benefit at much lower cost; the fallback is typically unevaluated and therefore a behaviour change during an incident (D-440), and it forfeits per-task selection |
| **Third-party AI gateway product** | Fast to adopt, handles normalization; inserts a dependency in the path of the core capability, adds a processor of tenant data, and surrenders control of routing and caching policy (D-238) |
| **Full multi-provider** *(chosen)* | Resilience, per-task quality, price leverage, tenant governance — at a genuine and stated cost |

**Trade-offs — stated plainly, because this is the expensive choice:**

- **N adapters to build, test and maintain**, each tracking a provider's API
  evolution independently.
- **Normalization is lossy in both directions.** Some provider features have no
  equivalent elsewhere; some have subtly different semantics that are worse than
  no equivalent because they look interchangeable.
- **Evaluation cost multiplies.** Meaningful per-workflow routing requires
  evaluating each workflow against each candidate model (`57`), and that is real
  inference spend on a recurring basis.
- **Cognitive load** (`23`'s complexity budget) — engineers must reason about
  more than one model's behaviour.

**Benefits.** No single-provider dependency on the core capability. Per-task
quality and cost optimization backed by evidence. Tenant-specific governance is
satisfiable. Capacity headroom across independent rate limits.

**Long-term impact.** Model providers will change more than any other part of the
stack (`23`: 1–3 year churn). An architecture that can absorb a new provider in
days and retire one without a rewrite is the difference between riding that churn
and being dragged by it.

---

## Model Registry

The single catalogue of what may be called, and the source of truth for routing.

| Attribute | Purpose |
| --- | --- |
| `model_id` | **Pinned, explicit provider identifier** — never an alias tracking "latest" (D-279) |
| `provider` | Adapter to use |
| `tier` | fast · balanced · frontier (D-94) |
| `capabilities` | Structured descriptor (below) |
| `context_window`, `max_output` | Hard limits used by the context budgeter |
| `pricing` | Input, output, and **cached-input** rates — cached reads are cheaper and must be priced separately or cost accounting is wrong |
| `status` | active · trial · deprecated · retired |
| `evaluation_scores` | Per workflow, from `57` — the evidence routing decisions rest on |
| `data_governance` | Region, training-use commitment, sub-processor status, certifications |
| `deprecation_date` | Provider's announced retirement, tracked (`27`) |

**Registry entries are configuration, reviewed like code, not runtime-editable.**
Adding a model, changing its tier, or changing its status is a governed change —
because each alters system behaviour exactly as a code change does (D-280).

---

## Capability Modelling

**Decision.** Workflows declare the **capabilities** they require; the registry
declares what each model **provides**; the gateway matches. Workflows never name
a model.

**Reasoning.** A workflow that names `claude-opus-5` breaks when that model
retires and cannot benefit from a better option. A workflow that declares
`{structured_output: strict, context: >150k, streaming: true, tool_use: false}`
remains correct across a decade of model turnover — the registry changes, the
workflow does not.

This is D-439's capability-based abstraction made concrete and mandatory.

**Capability descriptor:**

| Capability | Values | Why it matters |
| --- | --- | --- |
| `structured_output` | none · json_mode · strict_schema · tool_schema | Determines whether schema conformance is enforced by the provider or by our validator |
| `tool_use` | none · sequential · parallel | Agent steps (`56`) require it |
| `streaming` | none · text · text_and_tools | P-6 first-token budget |
| `context_window` | tokens | Budgeting |
| `prompt_caching` | none · automatic · explicit_breakpoints · managed_context | **Differs materially per provider** — see below |
| `reasoning_mode` | none · extended · configurable | Some tasks benefit substantially |
| `vision` | none · images · documents | Uploaded brief processing |
| `system_prompt` | separate_param · first_message · instruction_field | Normalization target |
| `deterministic_seed` | supported · not | Evaluation reproducibility |

**Capability degradation is explicit, never silent.** If no available model
provides a required capability, the gateway fails the call with a clear reason —
it does not substitute a model that "mostly" works. A workflow needing strict
schema enforcement running against a model with only JSON mode produces a higher
malformed-output rate that would appear as a mysterious quality regression.

---

## Normalization

The adapter layer's job: present one interface over genuinely different APIs.

| Difference | Normalization |
| --- | --- |
| **System prompt placement** | Anthropic: separate parameter · OpenAI: role message · Gemini: `systemInstruction` → one `system` concept, placed correctly per adapter |
| **Message roles** | Differing role vocabularies and alternation rules → canonical roles, adapter enforces provider constraints |
| **Tool/function schemas** | Different JSON shapes and nesting → one canonical tool declaration, translated per provider |
| **Stop reasons** | `end_turn`/`stop`/`STOP`/`length`/`max_tokens` → canonical `completed · truncated · tool_call · filtered · error` |
| **Token accounting** | Different tokenizers and reporting fields, cached vs uncached split → canonical usage record with cached tokens separated |
| **Streaming events** | Different SSE event shapes and deltas → canonical stream events |
| **Errors** | Different codes and retry semantics → canonical taxonomy mapped to `41`'s failure shapes |
| **Rate-limit signalling** | Different headers and reset semantics → canonical limit state feeding the token bucket (D-410) |

### Prompt caching — the most consequential difference

Providers implement prefix caching differently enough that a single abstraction
is genuinely hard, and getting it wrong forfeits the largest cost lever we have
(D-100).

| Provider style | Mechanism | Gateway handling |
| --- | --- | --- |
| **Explicit breakpoints** | Caller marks cacheable prefix boundaries; short TTL; minimum token threshold | Gateway inserts breakpoints at the stable/volatile boundary the Prompt Engine already produces |
| **Automatic prefix caching** | Provider caches identical prefixes transparently | Gateway ensures byte-identical prefixes across calls — which requires deterministic prompt rendering (`52`) |
| **Managed context objects** | Cached content created as a separate resource with its own lifecycle and TTL, then referenced | Gateway manages the cached-object lifecycle: create, reference, refresh, expire |

**The unifying abstraction is intent, not mechanism:** the Prompt Engine declares
*which portion of the prompt is stable*, and each adapter realizes that intent in
its provider's terms. This is why deterministic rendering (`52`) is a hard
requirement rather than a nicety — automatic prefix caching silently fails if
rendering varies by a single byte.

---

## Provider-Specific Escape Hatches

**Decision.** Workflows may request provider-specific features through a declared
extension mechanism, and doing so records a **portability cost** on the workflow.

**Reasoning.** Refusing all provider-specific capability produces exactly the
lowest-common-denominator outcome `06` warned against. Allowing it silently
produces workflows that are secretly single-provider, discovered only when the
provider fails.

Declared extensions give both: the capability is available, and the workflow's
reduced portability is visible in the registry, in routing, and in the failover
plan.

**Rule:** a workflow using an extension must declare its fallback behaviour —
degrade gracefully, or fail. There is no third option, and "it will probably be
fine" is not a fallback.

---

## Per-Tenant Provider Governance

**Decision.** Each tenant has an **allowed-provider set**, and routing never
selects outside it.

**Reasoning, and this is the multi-provider requirement most often missed.**
Providers are not equivalent from a compliance standpoint: they differ in
processing region, sub-processor chains, training-use commitments, corporate
jurisdiction and certifications. An enterprise tenant may contractually require
EU-only processing, or exclude specific jurisdictions, or permit only providers
on their approved vendor list.

A platform that routes tenant content to whichever model scored best cannot
satisfy those tenants at all — and the constraint arrives with the first
enterprise deal (`19` BR-5), not later.

This connects directly to `48`'s processor role: we process tenant content on the
tenant's instruction, and *which sub-processors we use* is part of that
instruction.

**Mechanism:**

- Default allowed set per plan tier; tenant-level override by contract.
- The gateway filters candidate models by the tenant's set **before** any other
  routing consideration — governance precedes optimization.
- If the allowed set contains no model with the required capability, the call
  fails with a clear, tenant-visible reason rather than silently degrading.
- Every provider is a registered sub-processor (`48`), and the tenant is notified
  of additions.
- Per-tenant provider usage is auditable, because tenants will ask us to prove
  their constraint was honoured.

**Trade-offs.** Constrained tenants may get worse or costlier results, and must
be told that plainly at contract time rather than discovering it. Routing becomes
tenant-dependent, which complicates cache keys — resolved because AI response
caching is already tenant-partitioned (D-82).

---

## Adding a Provider

A governed process, because each addition expands the data-governance surface.

1. **Assess** — capabilities, pricing, region, training-use terms, certifications,
   stability. Registry entry drafted with `status: trial`.
2. **ADR** — why this provider, which workflows it is a candidate for, its
   data-governance attributes, and its exit path.
3. **Adapter** — implemented against the normalization contract above.
4. **Conformance suite** — a standard battery every adapter must pass: role
   handling, tool schemas, streaming, stop reasons, token accounting, error
   mapping, cache behaviour, cancellation, timeout handling.
5. **Evaluation** — the golden sets for candidate workflows run against it
   (`57`); scores recorded in the registry.
6. **Sub-processor registration** and tenant notification (`48`).
7. **Promote** to `active` for specific workflows where evidence supports it —
   never globally by default.

**The conformance suite is what makes provider addition safe rather than
adventurous.** Without it, each adapter's subtle divergences surface as
production defects attributed to prompts or models rather than to the adapter.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-550 | **Amends D-51** — genuine multi-provider support, not a single provider behind an abstraction | An abstraction that has never carried a second provider does not work; concentration risk on the differentiator is unacceptable |
| D-551 | Every model call passes through the gateway; provider SDKs importable only by adapters | Otherwise routing, budget, caching, audit and governance are all bypassable |
| D-552 | Model registry is the source of truth: pinned ids, capabilities, pricing, governance, evaluation scores | Routing must rest on recorded evidence, not on code comments |
| D-553 | Cached-input token pricing tracked separately from uncached | Cache reads are cheaper; conflating them makes cost accounting wrong |
| D-554 | Workflows declare required capabilities; they never name a model | A workflow naming a model breaks on retirement and cannot benefit from a better option |
| D-555 | Capability degradation is explicit; the gateway fails rather than substituting | Silent substitution appears later as a mysterious quality regression |
| D-556 | Prompt caching normalized by *intent* (stable prefix), realized per provider mechanism | The three mechanisms differ enough that only intent is portable |
| D-557 | Deterministic prompt rendering is a hard requirement of automatic prefix caching | A single byte of variance silently forfeits the largest cost lever |
| D-558 | Provider-specific extensions permitted, but record a declared portability cost and a fallback | Refusing them yields lowest-common-denominator; allowing them silently yields secretly single-provider workflows |
| D-559 | **Per-tenant allowed-provider set; governance filters candidates before optimization** | Providers differ in region, jurisdiction, sub-processors and training terms; enterprise tenants constrain this contractually |
| D-560 | A governance-constrained tenant with no capable model gets a clear failure, not a silent downgrade | The constraint is contractual; violating it quietly is worse than failing |
| D-561 | Every adapter must pass a standard conformance suite before `active` | Adapter divergences otherwise surface as defects blamed on prompts or models |
| D-562 | Providers promoted per workflow on evaluated evidence, never globally by default | Multi-provider is only real if routing rests on measurement |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Adapter maintenance burden exceeds capacity | Adapters drift; failover paths rot | Conformance suite runs continuously; a failing adapter is demoted to `trial` automatically |
| Normalization hides a semantic difference | Subtle quality or correctness divergence between providers | Conformance suite tests semantics, not only shape; cross-provider evaluation (`57`) |
| Evaluation cost multiplies across providers | Budget pressure; evaluation skipped | Tiered golden sets; full cross-provider runs only on candidate promotion |
| Provider-specific extensions proliferate | Workflows become effectively single-provider | Portability cost recorded and visible; fallback mandatory |
| Tenant provider constraint violated by a routing bug | Contractual breach | Governance filter precedes all routing; per-tenant provider usage audited and reportable |
| Pinned model retired with short notice | Forced migration | Deprecation dates tracked in the registry; evaluation of successors runs ahead of need |
| Registry becomes runtime-editable for convenience | Ungoverned behaviour change | Registry is reviewed configuration; changes follow the prompt deployment path (`53`) |

## Dependencies

- **Depends on:** AI strategy (`10`), AI integration (`40`), technology
  selection (`24`), GDPR (`48`).
- **Depended on by:** gateway (`51`), prompt engine (`52`), PromptOps (`53`,
  `54`), workflows and agents (`56`), evaluation (`57`), AI security (`58`).

## Future Improvements

- Write ADR-0002 (multi-provider) and ADR-0003 (bounded agents) before
  implementation, per the amendment table.
- Add self-hosted open-weight models as a registry provider for high-volume
  fast-tier tasks once volume justifies GPU operations (`24`).
- Publish the conformance suite specification before the second adapter is
  written, so it is a contract rather than a retrofit.
