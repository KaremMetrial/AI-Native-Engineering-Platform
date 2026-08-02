# AI Strategy

## Purpose

Define how AI is designed, grounded, evaluated, governed and paid for. This is
the product's differentiator and its largest source of novel technical risk.
"AI-native" here means AI operating on a structured, tenant-owned knowledge
graph as a governed participant in the delivery lifecycle — not a chat box
bolted to a CRUD application.

## Scope

**In scope:** AI capability design, grounding and retrieval, prompt and
workflow management, model routing, structured output, evaluation, human
oversight, guardrails, cost governance, and failure handling.

**Out of scope:** AI security controls in depth (`09-security-strategy.md`) and
service architecture (`05-high-level-architecture.md`).

---

## Principles

These constrain every AI feature and are the basis on which AI features are
rejected in review.

1. **AI drafts; humans decide.** Every generated artifact enters as a draft and
   becomes authoritative only on human approval (D-36). Non-negotiable —
   requirements and estimates carry contractual weight.
2. **Grounded, not generic.** Output derives from the tenant's own Delivery
   Graph. Ungrounded generation produces plausible, confident, wrong documents —
   the worst possible failure mode for this product.
3. **Structured, not prose.** Models return schema-validated structures
   (D-52). Parsing prose is fragile and silently lossy.
4. **Traceable.** Every artifact records model, prompt version, inputs,
   retrieved context, cost, and approver (U-4).
5. **Evaluated, not vibes-tested.** Quality is measured against a maintained
   golden set. "It looked good in the demo" is not evidence.
6. **Bounded in cost.** Every call is metered and attributed; every tenant has
   a cap.
7. **Degradable.** AI failure must never take down the platform (A-5).

**Principle 1 is the most likely to face pressure**, because full automation
demos better and sounds more ambitious. It is also what makes the product
trustworthy enough to sign a contract against. Removing the human gate would
convert a defensible system of record into a plausible-sounding document
generator — precisely the commodity we are avoiding.

---

## Capability Design

AI capabilities are designed as **workflows over the graph**, each with defined
inputs, schema-constrained outputs, quality criteria and cost profile — never as
open-ended chat.

| Capability | Inputs | Output | Tier |
| --- | --- | --- | --- |
| Discovery question generation | Idea, domain, prior discovery | Structured question set | Balanced |
| Requirement extraction | Discovery notes, uploaded documents | Candidate requirements with sources | Fast |
| BRD synthesis | Approved discovery, client context | Structured BRD draft | Frontier |
| SRS generation | Approved BRD | Functional/non-functional requirements, acceptance criteria | Frontier |
| Requirement quality review | Draft requirements | Ambiguity, conflict and gap findings | Balanced |
| Architecture proposal | SRS, constraints, prior architectures | Options with trade-offs and rationale | Frontier |
| Data model proposal | SRS, architecture | Entities, relationships, constraints | Frontier |
| API contract generation | SRS, data model | Contract specification | Balanced |
| Estimation assistance | SRS, historical actuals | Ranged estimate with drivers | Frontier |
| Proposal drafting | Approved scope, estimate, template | Client-ready proposal | Balanced |
| Task generation | Approved SRS, architecture | Tasks with acceptance criteria | Balanced |
| Test case generation | Acceptance criteria | Test cases | Balanced |
| Change impact analysis | Changed artifact, graph | Affected artifacts, effort delta | Balanced |
| Classification / routing | Any content | Labels, entities | Fast |

**Why workflows rather than a general assistant:** a general chat interface is
unevaluable (no defined correct output), uncontrollable in cost, unbounded in
scope, and produces output that cannot be linked into the graph. Defined
workflows are testable, cacheable, routable by cost tier, and produce artifacts
with structure the rest of the platform can reason about.

A conversational interface may later sit *on top of* these workflows as an
invocation surface — but the workflows remain the unit of capability.

---

## Grounding and Retrieval

Grounding quality is the product's real differentiator. As models commoditize,
what distinguishes output is the quality of the context supplied — and only we
have the tenant's Delivery Graph.

### Context assembly

For each workflow, context is assembled deliberately rather than by dumping
everything available:

1. **Structural context** — the graph neighbourhood: the artifacts this one
   derives from, their approved versions, their linked decisions. This is
   traversal, not search, and it is the highest-signal context available.
2. **Semantic context** — vector retrieval over tenant content for relevant
   prior work, patterns and decisions.
3. **Organizational context** — tenant conventions, templates, terminology,
   standards.
4. **Historical context** — for estimation specifically, this tenant's actual
   outcomes on comparable work. **The mechanism behind G2**, and something no
   competitor without a graph can offer.

**Structural context is weighted above semantic retrieval.** Knowing that this
SRS derives from *these three approved requirements* is far more valuable than
five semantically similar paragraphs — and it is exact rather than approximate.
Vector search supplements traversal; it does not replace it.

### Retrieval constraints

- **Tenant filter applied before retrieval, never after** (D-23, T-7). Non-
  negotiable and independently tested.
- **Only approved artifacts** are retrieved as authoritative context. Retrieving
  unapproved drafts compounds model error through the graph.
- **Precision over recall.** More context is not better: it costs more (D-73)
  and degrades output by diluting signal. Retrieval size is tuned as a quality
  parameter, not maximized.
- **Context is recorded in lineage** so any output can be explained after the
  fact — which is what makes a disputed artifact defensible.

**On RAG's known weakness:** naive vector retrieval returns semantically similar
but logically irrelevant content. Our mitigation is that the primary retrieval
mechanism is *graph traversal over explicit relationships* — we know exactly
which artifacts are relevant because the links say so. This is the practical
payoff of building the graph first, and it is why the moat argument in `01` is
technical rather than aspirational.

---

## Prompt and Workflow Management

Prompts are **versioned code artifacts**, not configuration edited in a UI.

- Stored in the repository, code-reviewed, versioned.
- Every generation records the exact prompt version used.
- Changes go through evaluation before release (below).
- Rollback is a deployment, not an emergency edit.
- A/B comparison supported for measured improvement.

**Why in the repository rather than a database-backed editor:** a prompt change
is a behaviour change with the same blast radius as a code change. Editing
production prompts through an admin UI means unreviewed, untested, unversioned
changes to the system's most behaviour-defining component — and no way to
correlate a quality regression with what caused it.

**Trade-off accepted:** non-engineers cannot tune prompts directly, and
iteration is slower than live editing. Mitigated by fast deployment of the AI
service (D-42's independent cadence) and by an evaluation harness that makes
iteration *safe* rather than merely fast. Speed without evaluation is how
quality silently regresses.

---

## Model Routing

Task-tier routing, behind the provider abstraction (D-51).

| Tier | Used for | Optimizes |
| --- | --- | --- |
| **Fast** (Haiku class) | Classification, extraction, routing, simple transforms | Cost and latency |
| **Balanced** (Sonnet class) | Most generation: tasks, tests, proposals, reviews | Quality/cost balance |
| **Frontier** (Opus class) | Synthesis and reasoning: BRD, SRS, architecture, estimation | Quality |

**Routing is per workflow, not per user request** — the workflow knows what it
needs. Users do not choose models; that would be a cost-control hole and a
quality lottery, and users have no basis for the choice.

Escalation is permitted: if a fast-tier output fails schema validation or a
confidence check, the workflow may retry at a higher tier. Bounded, since
uncontrolled escalation defeats the cost model.

**Why not always use the frontier model:** cost differences between tiers are
roughly an order of magnitude. Using a frontier model to classify a document
type is indefensible economics with no quality benefit — the fast model is
equally correct at that task. Conversely, using a fast model for architecture
synthesis produces output that fails G4 and destroys trust. **Tier selection is
an engineering decision made per workflow and validated by evaluation**, not a
default or a preference.

Concrete model identifiers are configuration, reviewed as models are released.

---

## Structured Output

All workflow outputs are schema-constrained (D-52).

- Schemas defined once, shared between the AI service and the core (single
  source of truth).
- Responses validated on receipt; invalid responses are retried with corrective
  feedback, then escalated a tier, then failed cleanly.
- **A failed generation is a clean failure, never a partial or coerced
  artifact.** Salvaging malformed output produces subtly corrupt artifacts that
  propagate through the graph — far worse than an honest failure the user can
  retry.

---

## Evaluation

Without evaluation, AI quality is unknown, regressions are invisible, and G4 is
unmeasurable.

### Evaluation layers

| Layer | Method | Runs |
| --- | --- | --- |
| **Schema conformance** | Deterministic validation | Every call, production |
| **Deterministic assertions** | Required sections present, traceability links resolve, no contradictions with source | Every eval run |
| **Golden set comparison** | Curated inputs with expert-reviewed reference outputs | CI on prompt/model change |
| **LLM-as-judge** | Model scores output on rubrics (completeness, specificity, groundedness, absence of fabrication) | CI on change |
| **Human review sampling** | Experts score a production sample | Weekly |
| **Production signal** | Approval rate, edit distance, regeneration rate (G4) | Continuous |

### How evaluation gates releases

Because model output is non-deterministic, **per-run pass/fail is the wrong
gate** — it produces flaky, ignored tests. Instead:

- Each workflow has quality thresholds on aggregate scores across the golden
  set.
- A prompt or model change must **not regress** aggregate scores beyond a
  tolerance.
- Regression blocks release; improvement is recorded as the new baseline.
- Golden sets grow from real production failures — every quality incident adds
  a case, so the suite hardens against exactly the failures we have seen.

**LLM-as-judge honestly assessed:** it correlates imperfectly with human
judgment, and can share the biases of the model being evaluated. It is used
because it scales and catches large regressions cheaply — but it is calibrated
against human review samples, never trusted alone. The production signals (G4)
are the ground truth; everything else is an early-warning system.

---

## Human Oversight

| Control | Implementation |
| --- | --- |
| Draft-by-default | AI output is never authoritative without approval (D-36) |
| Full editability (U-5) | Users edit before approval; edits are captured as training signal for future evaluation |
| Provenance display (U-4) | Model, prompt version, inputs and confidence shown on every artifact |
| Source attribution | Generated claims link to the artifacts they derive from, so a reviewer can verify rather than trust |
| Uncertainty surfacing | Workflows explicitly emit assumptions and gaps rather than silently inventing detail |
| Regeneration with guidance | Users steer regeneration instead of rewriting from scratch |

**Uncertainty surfacing is the highest-leverage trust feature.** A BRD that
states "the payment provider was not specified in discovery — assumed Stripe"
is enormously more trustworthy than one that silently assumes it. The failure
mode we most need to prevent is **confident fabrication**, and the mitigation
is making the model's gaps explicit rather than hoping it has none.

---

## Guardrails

| Guardrail | Purpose |
| --- | --- |
| Untrusted content isolation | Prompt injection containment (SEC-9, D-80) |
| Tool access minimization | Bound what a compromised workflow can do |
| Tenant-scoped retrieval | Prevent cross-tenant leakage (T-7) |
| Output schema validation | Prevent malformed artifacts |
| PII redaction where applicable | Minimize data sent to providers |
| Loop and recursion limits | Prevent denial-of-wallet |
| Output length limits | Cost and abuse control |
| Content safety checks | Prevent inappropriate output in client-facing artifacts |

---

## Cost Governance

Directly protects G5 and $-1 through $-4. In an AI product, cost governance is
an engineering responsibility, not a finance report.

**Measurement (Phase 1):** every call records tokens, model and cost, attributed
to tenant, project, workflow and user. Cost per artifact type is tracked as a
trend ($-2). Reconciled against provider invoices monthly — an unreconciled
internal estimate drifts.

**Control (Phase 2):** per-tenant spend caps with warning thresholds; per-
workflow budgets; caps enforced *before* the call, not after.

**Optimization (continuous):**

| Lever | Impact |
| --- | --- |
| Task-tier routing | Largest single lever — roughly 10× between tiers |
| Response caching (D-72) | Eliminates repeat cost entirely on identical inputs |
| Prompt caching | Substantial saving on the large stable context prefixes our workflows use |
| Context precision (D-73) | Directly proportional to token cost |
| Output length discipline | Output tokens typically cost more than input |
| Batch processing | Lower rates where latency is not user-facing |

**Prompt caching deserves emphasis** given our access pattern: workflows share
large, stable context prefixes (tenant conventions, templates, standards) across
many calls. This is close to the ideal case for provider-side prompt caching,
and designing context assembly so the stable portion comes first — deliberately,
from the start — turns a significant recurring cost into a small one.

---

## Failure Handling

| Failure | Response |
| --- | --- |
| Provider unavailable | Fail over to alternate provider; if none, queue and retry with backoff |
| Rate limited | Backoff with jitter; spread across providers; backpressure to workers |
| Timeout | Bounded retry, then clean failure with a clear user message |
| Invalid output | Retry with corrective feedback → escalate tier → clean failure |
| Budget exceeded | Reject before calling; notify tenant admin with a clear reason |
| Total AI outage | **Platform remains fully functional for all non-AI operations (A-5).** Users can read, edit, approve and manage everything manually. |

**The last row is the requirement that matters.** An outage at a model provider
must degrade the product to "a good delivery platform without AI assistance,"
never to "an unavailable product." This is a direct architectural consequence
of D-35 and the reason AI is never in a synchronous request path.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-88 | AI produces drafts; human approval confers authority | Requirements and estimates carry contractual weight |
| D-89 | Capabilities are defined workflows, not open chat | Only workflows are evaluable, cacheable, cost-routable and graph-linkable |
| D-90 | Graph traversal is the primary grounding; vector search supplements | Explicit relationships beat semantic similarity for relevance and exactness |
| D-91 | Only approved artifacts are retrieved as authoritative context | Prevents model error compounding through the graph |
| D-92 | Retrieval precision over recall | More context costs more and dilutes signal |
| D-93 | Prompts are versioned repository artifacts, not UI-edited config | A prompt change is a behaviour change with code-level blast radius |
| D-94 | Model tier chosen per workflow, never by the user | Users lack the basis; user choice is a cost-control hole |
| D-95 | Invalid output fails cleanly; never salvaged | Partial artifacts propagate silent corruption |
| D-96 | Evaluation gates on aggregate thresholds, not per-run pass/fail | Non-determinism makes per-run gating flaky and therefore ignored |
| D-97 | LLM-as-judge calibrated against human review, never trusted alone | Imperfect correlation and shared model bias |
| D-98 | Golden sets grow from production failures | Suite hardens against failures actually observed |
| D-99 | Workflows surface assumptions and gaps explicitly | Confident fabrication is the most damaging failure mode |
| D-100 | Context assembled stable-prefix-first for prompt caching | Turns a large recurring cost into a small one |
| D-101 | Cost metering from Phase 1, caps from Phase 2 | Cannot control what is not measured (G5) |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Output quality below the usefulness threshold | G4 fails; reviewing costs more than writing | Evaluation from Phase 1; narrow AI surface until thresholds are met |
| Confident fabrication accepted by a reviewer and signed | Contractual exposure for the customer | Source attribution, uncertainty surfacing, human approval, sampled review |
| Inference cost outpaces revenue | Margin collapse | Tier routing, caching, context discipline, per-tenant caps |
| Provider dependency (pricing, availability, terms) | Business and availability risk | Provider abstraction, evaluated fallbacks, graceful degradation |
| Prompt injection via client documents | Unauthorized action or disclosure | Architectural containment (D-80, D-81) |
| Evaluation harness becomes stale | Quality regressions ship undetected | Golden sets grow from incidents; human calibration sampling |
| Model capability advances make our workflows obsolete | Rewriting the AI layer | Deliberately the most replaceable component (D-42); the graph, not the workflows, is the moat |
| Human approval treated as a rubber stamp under time pressure | Oversight becomes theatre | Track edit distance and approval time as quality signals; surface uncertainty prominently |

## Dependencies

- **Depends on:** module boundaries (`03`), NFRs (`04`), architecture (`05`),
  technology (`06`), tenancy (`07`), security (`09`).
- **Depended on by:** testing strategy (evaluation lane), quality gates,
  roadmap.

## Future Improvements

- Publish per-workflow evaluation rubrics and initial golden sets before the
  first workflow ships.
- Instrument edit distance between generated and approved versions as the
  primary G4 signal.
- Evaluate self-hosted models for high-volume fast-tier tasks once volume
  justifies the operational cost.
- Explore per-tenant calibration — learning each tenant's conventions and style
  from their approval edits — once sufficient approval data exists.
