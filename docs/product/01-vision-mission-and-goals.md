# Vision, Mission, Business Goals and Market Positioning

## Purpose

Establish why this platform exists, what it is trying to become, and how it
wins. Every downstream architectural decision is justified against this
document. If a technical decision does not serve a goal stated here, it is
scope creep.

## Scope

**In scope:** product vision, mission, business goals with measurable targets,
market positioning, competitive differentiation, and the strategic bet the
architecture must protect.

**Out of scope:** pricing mechanics, go-to-market execution, sales
compensation, brand identity. Those are business artifacts, not engineering
foundations.

---

## Vision

**Any software company can take an idea from a single sentence to a delivered,
maintained product — without losing the thread.**

Today the thread breaks constantly. A client's intent is captured in a sales
call, rewritten in a proposal, re-derived in a requirements doc, reinterpreted
by an architect, re-specified in tickets, and finally guessed at by a developer
six weeks later. Every hop loses fidelity. Rework, scope disputes, and blown
estimates are all symptoms of the same disease: **context does not survive the
handoffs.**

## Mission

Build the system of record for software delivery, where every artifact —
discovery notes, BRD, SRS, architecture, schema, API contract, estimate,
proposal, contract, task, commit, test, deployment — is a **linked, versioned
node in one graph**, and AI operates on that graph as a first-class participant
rather than as a detached chat window.

## The Strategic Bet

The defensible asset is not "AI that writes documents." Any competitor can
prompt a frontier model to draft a BRD by the end of the quarter.

The defensible asset is the **Delivery Graph**: the accumulated, traceable,
tenant-owned chain of *why* — linking a business objective to the requirement
it produced, the architectural decision that satisfied it, the tasks that
implemented it, the tests that verify it, and the deployment that shipped it.

This bet drives three non-negotiable architectural consequences, and they are
the reason the rest of this documentation set looks the way it does:

1. **Traceability is a core domain concern, not a reporting feature.** It
   cannot be bolted on later. Links between artifacts are modelled explicitly
   from the first migration.
2. **Every artifact is versioned and attributable.** Who or what produced this,
   from which inputs, approved by whom. This is what makes AI output
   trustworthy enough to sign a contract against.
3. **AI is grounded in the tenant's own graph, never in generic world
   knowledge alone.** Retrieval scope is a security boundary and a quality
   boundary simultaneously.

### Why this and not the alternatives

| Strategy | Why not chosen |
| --- | --- |
| **AI document generator** (fastest to build) | No moat. Output quality converges across vendors as models improve; the product becomes a thin prompt wrapper competing on price. |
| **Better project management tool** | Mature, brutally competitive category (Jira, Linear, Asana). Switching costs are high and the incumbent advantage is distribution, not product. |
| **AI coding agent** | Crowded, capital-intensive, and starts at the *wrong end* of the lifecycle. The expensive failures in software delivery happen upstream of code — in mis-specified requirements and mis-priced proposals. |
| **Delivery Graph + AI on top** (chosen) | The graph compounds in value per tenant over time, is genuinely hard to replicate, and gets *better* as models commoditize, because grounding quality — not model access — becomes the differentiator. |

**Trade-off accepted:** this is the slowest path to a demo and the hardest to
build. A document generator ships in weeks; a coherent graph-backed lifecycle
takes quarters. We accept slower initial velocity in exchange for a moat that
survives model commoditization. The roadmap (`docs/governance/20-roadmap.md`)
mitigates this by sequencing modules so that revenue-generating slices ship
early even though the full lifecycle does not.

---

## Core Business Goals

Goals are stated with the metric that proves them. Targets are directional
commitments for planning, not contractual guarantees.

### G1 — Compress the pre-sales cycle

Reduce time from initial client conversation to a defensible, priced proposal
from the industry-typical 2–4 weeks to under 48 hours.

- **Metric:** median hours, first discovery session → proposal sent.
- **Target:** < 48h for the median engagement by end of Phase 2.
- **Why it matters:** this is the single most acute, most quantifiable pain for
  the beachhead customer, and it is where willingness to pay is highest. Agencies
  lose deals to slower response times and burn unbilled senior hours on
  proposals that never convert.

### G2 — Make estimates defensible

Reduce variance between estimated and actual delivery effort.

- **Metric:** mean absolute percentage error of estimate vs. actual, per project.
- **Target:** MAPE < 25% by end of Phase 3, measured on projects that ran their
  full lifecycle on the platform.
- **Why it matters:** estimate error is where agency margin dies. This metric is
  only achievable *because* of the Delivery Graph — actuals flow back and
  calibrate future estimates per tenant. It is the clearest proof that the
  strategic bet pays off, and it is the metric a competitor without a graph
  cannot chase.

### G3 — Preserve context across the lifecycle

Every implementation task is traceable to the requirement that justifies it.

- **Metric:** percentage of tasks with an unbroken link path to a BRD objective.
- **Target:** > 90% on platform-managed projects.
- **Why it matters:** this is the leading indicator that the graph is real
  rather than decorative. If teams route around the links, the moat is fiction
  and we must know early.

### G4 — Earn trust in AI output

AI-generated artifacts are accepted by humans with light editing, not rewritten.

- **Metric:** approval rate of AI-drafted artifacts without major revision;
  edit distance between generated draft and approved version.
- **Target:** > 70% approved with minor edits by end of Phase 3.
- **Why it matters:** below this threshold the product is *negative* value —
  reviewing bad AI output costs more than writing from scratch. This metric
  gates whether we scale AI surface area or narrow it.

### G5 — Build a durable commercial engine

- **Metric:** net revenue retention; gross margin after AI inference cost.
- **Target:** NRR > 115%; gross margin > 70% at steady state.
- **Why it matters:** margin is the goal most likely to be quietly destroyed by
  engineering decisions. Unmetered AI inference can take gross margin negative
  on a flat-rate plan. This is why per-tenant cost metering is a Phase 1
  requirement (`docs/architecture/10-ai-strategy.md`), not a later optimization.

---

## Market Positioning

### Category

**AI-native software delivery platform.** Deliberately not "project management
with AI," which anchors buyers to Jira's price point and Jira's feature
expectations.

### Positioning statement

For software agencies and product organizations who lose margin and credibility
in the gap between what a client asked for and what gets built, the platform is
an AI-native delivery system that carries a single, traceable thread of intent
from idea to production. Unlike point tools that automate one stage and
document generators that produce disconnected artifacts, it keeps every
decision linked to the requirement that justifies it.

### Competitive landscape

| Segment | Examples | Their strength | The gap we exploit |
| --- | --- | --- | --- |
| Project management | Jira, Linear, Asana | Execution tracking, ecosystem, distribution | Blind to everything upstream of a ticket. A ticket has no memory of the requirement that produced it. |
| Docs & knowledge | Notion, Confluence | Flexible authoring, adoption | Unstructured prose. No semantics, no traceability, no enforcement. |
| Generic AI chat | Claude, ChatGPT | Raw capability, ubiquity | Stateless relative to the project. Context is re-pasted per session and lost after it. |
| AI coding agents | Copilot, Cursor, Devin | Genuine code-stage leverage | Enter after the expensive decisions are already made and already wrong. |
| Estimation tools | Various point tools | Focused | No connection to requirements or actuals; estimates never calibrate. |

**Honest assessment of our weakness:** we are last to market in a landscape
where incumbents own distribution, and the platform is only valuable once a
tenant has invested real project data in it. Cold-start cost is our primary
commercial risk, tracked in `docs/governance/19-risk-register.md` (BR-2).
Integrations that ingest existing artifacts — rather than demanding greenfield
entry — are the designed mitigation, which is why the Integrations context is
scheduled early rather than treated as a Phase 4 convenience.

### Beachhead

**Digital agencies and software consultancies, 20–200 engineers.**

Chosen because they feel every pain in the lifecycle *and* pay for it directly:
they write proposals constantly, live or die on estimate accuracy, and carry
the cost of requirement churn as unbillable rework. They also buy quickly, with
a founder or delivery director able to sign without a nine-month procurement
cycle.

Expansion path — internal product teams at mid-market companies, then
enterprise IT — is deliberately sequenced *after* the beachhead, because
enterprise entry demands SSO, SCIM, audit, data residency and SOC 2 that we do
not intend to fund in Phase 1. The architecture must not preclude that path,
which is why multi-tenancy and audit are designed to enterprise standards from
the start even though they are not sold to enterprises yet.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-01 | The Delivery Graph is the core product asset | Only asset that compounds and resists model commoditization |
| D-02 | Traceability is a domain concern from day one | Retrofitting links onto existing artifacts is a rewrite, not a migration |
| D-03 | Beachhead is agencies of 20–200 engineers | Highest acute pain, shortest sales cycle, direct budget authority |
| D-04 | Category is delivery platform, not project management | Avoids anchoring to incumbent pricing and feature expectations |
| D-05 | Enterprise-grade tenancy and audit built in Phase 1 despite SMB beachhead | Retrofitting tenant isolation is the single most expensive possible rework |
| D-06 | Per-tenant AI cost metering is a Phase 1 requirement | Protects G5; unmetered inference can invert gross margin |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Graph is built but users route around it, working in docs and chat instead | Strategic bet fails; product degrades to a document generator | G3 measured from Phase 1; if links are not forming, re-examine UX before adding modules |
| Frontier models improve enough that generic chat closes the gap | Differentiation erodes | Moat is grounding and traceability, not generation quality; this improves as models improve |
| Beachhead too small to sustain the company | Growth ceiling | Expansion path designed in; architecture does not preclude enterprise |
| Slower build than a document-generator competitor | Lost first-mover position | Roadmap ships revenue slices early (Phase 1–2) rather than waiting for the full lifecycle |
| AI inference cost scales faster than revenue | Margin collapse (G5) | Model routing by task tier, caching, per-tenant budgets — see AI strategy |

## Dependencies

- **Depends on:** the engineering charter (`docs/engineering/MASTER_SYSTEM_PROMPT.md`)
  for the quality bar all of this is built to.
- **Depended on by:** every other foundation document. The core modules,
  non-functional requirements, and roadmap all trace their justification here.

## Future Improvements

- Replace directional targets with observed baselines once Phase 1 telemetry
  exists. Every target in this document is a hypothesis until real tenants
  generate real data.
- Add a per-persona value narrative once design partner interviews are complete.
- Revisit positioning after the first ten closed deals; the language that
  actually closes business rarely matches the language written before it.
