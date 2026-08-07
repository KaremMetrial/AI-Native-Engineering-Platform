# Core Modules and Functional Scope

## Purpose

Decompose the platform into bounded contexts with explicit ownership,
responsibilities, and boundaries; then bound what is built when. This document
is the source of truth for module boundaries — the modular monolith in
`docs/architecture/05-high-level-architecture.md` enforces exactly these seams
in code.

## Scope

**In scope:** bounded contexts, their responsibilities and relationships, the
Delivery Graph as shared substrate, and phased functional scope with explicit
exclusions.

**Out of scope:** entity-level data modelling, API surface, screen design.
Those are implementation deliverables produced after this foundation is
approved.

---

## Decomposition Principle

Modules are **bounded contexts** derived from business capability and from
where the language changes — not from technical layering. A "Controllers"
module or a "Services" module would be a layer masquerading as a boundary and
would produce a distributed ball of mud.

Three rules govern every boundary:

1. **A module owns its data.** No module reads another module's tables
   directly. Cross-module reads go through a published interface.
2. **Cross-module communication is explicit** — a synchronous call to a
   published application service, or an asynchronous domain event. Never a
   shared model or a shared table.
3. **Boundaries are mechanically enforced.** Static analysis fails the build on
   violation (`docs/delivery/13-coding-standards-strategy.md`). A convention
   that is not enforced is a convention that is already broken.

---

## The Delivery Graph (shared substrate)

Not a module. The **kernel** every module depends on, and the one piece of
shared model the boundary rules deliberately permit.

**Responsibility:** identity, versioning, linkage, and lineage of every
delivery artifact.

- Artifact identity and immutable version history
- Typed links between artifacts (`derives_from`, `satisfies`, `implements`,
  `verifies`, `supersedes`, `references`)
- Lineage: which inputs, which model, which prompt version, which human
  approved it
- Traversal and impact analysis: "what does changing this requirement affect?"

**Why a shared kernel rather than per-module linkage:** traceability is
inherently cross-context. Distributing it would mean every module reimplements
linking and no module can answer a global question. The kernel is deliberately
**small and stable** — identity, versions, links, lineage, and nothing else. It
holds no business rules. Business meaning lives in the owning module.

**Risk accepted:** a shared kernel is a coupling point and a potential
bottleneck. Mitigated by keeping its surface minimal, versioning it strictly,
and treating changes to it as architecturally significant (ADR required).

---

## Bounded Contexts

Phase column refers to `docs/governance/20-roadmap.md`.

### C1 — Identity and Tenancy · Phase 1

Tenants, organizations, users, memberships, roles, permissions, sessions,
external stakeholder access, SSO/SCIM.

Foundational: every other context depends on tenant and actor resolution. Owns
the isolation boundary all other modules inherit.

**Build/buy note — resolved, see `docs/architecture/adr/0001-metrial-auth-evaluation-outcome.md`:**
`metrial-auth` was described as an existing Laravel enterprise identity
platform in-house, covering SSO, SAML/OIDC, SCIM, WebAuthn, ABAC and adaptive
MFA, whose reuse would remove months from the critical path and reduce the
riskiest security surface. The Phase 0 evaluation this note called for found
the package's actual location unreachable — not incompatible, unreachable —
after checking public registries and the project owner directly. ADR-0001
closes this by building C1 identity in-house instead, designed against `07`'s
tenancy model directly rather than adapted to an external package's.

### C2 — Discovery · Phase 1

Idea intake, structured elicitation, stakeholder interviews, assumption and
constraint capture, client context.

Separate from Requirements because its language is exploratory and its outputs
are *unvalidated*. Collapsing discovery into requirements is how unverified
assumptions get laundered into signed commitments.

### C3 — Requirements · Phase 1

BRD, SRS, functional and non-functional requirements, acceptance criteria,
requirement versioning and change control, traceability matrix.

The most valuable context in the system: the input to nearly every downstream
generation task and the anchor of every trace path.

### C4 — Design · Phase 2

Architecture design, ADRs, data model design, API contract design, integration
design, technology recommendations.

Distinct from Requirements because it answers *how* against a fixed *what*, and
because its artifacts have different lifecycles and reviewers.

### C5 — Estimation · Phase 2

Effort estimation, cost modelling, timeline projection, capacity modelling,
risk-adjusted ranges, and calibration from historical actuals.

Its own context because estimation logic is genuinely complex, is the
platform's highest-value differentiator (G2), and must evolve independently of
both planning and commercial concerns.

### C6 — Commercial · Phase 2

Proposals, pricing, contracts, statements of work, approval workflows,
e-signature integration, client-facing presentation.

Isolated for a security reason as much as a domain reason: commercial data has
a different confidentiality class and a different audience (P8 external
stakeholders). Keeping it separate makes the access boundary explicit rather
than emergent.

### C7 — Planning · Phase 3

Work breakdown, task generation, dependencies, sprint planning, capacity
allocation, milestones, re-planning on scope change.

### C8 — Execution · Phase 3

Developer workspace, task context assembly, code integration state, branch and
PR linkage, technical notes.

The context where the graph proves its worth or fails: it is where a developer
either receives full context automatically or goes looking for it elsewhere.

### C9 — Quality · Phase 3

Test planning, test case generation from acceptance criteria, defect
management, verification results, quality gate status.

### C10 — Operations · Phase 4

Environments, release coordination, deployment records, incident and
maintenance tracking, support workflows.

### C11 — AI Orchestration · Phase 1 *(cross-cutting)*

Agent and workflow definitions, prompt registry and versioning, model routing,
retrieval, evaluation harness, guardrails, cost accounting.

**Deliberately a context, not a library.** Every module needs AI; if each
implements its own prompting, we get eleven divergent implementations, no
central cost control, no consistent guardrails, and no way to evaluate quality.
Modules describe *what* they need generated; this context owns *how*. See
`docs/architecture/10-ai-strategy.md`.

### C12 — Integrations · Phase 2

Git providers, issue trackers, communication tools, calendars, cloud
providers, e-signature; webhook ingestion and outbound sync.

Scheduled early despite feeling like a "later" concern because it is the
designed mitigation for the cold-start problem (BR-2) — meeting tenants where
their data already lives instead of demanding greenfield entry.

### C13 — Analytics and Reporting · Phase 3

Portfolio health, estimate-vs-actual, delivery velocity, AI usage and quality
metrics, executive reporting.

Reads across contexts via published read models and events — never by reaching
into other modules' tables. The rule that keeps analytics from silently
becoming the coupling that defeats modularity.

### C14 — Billing and Subscription · Phase 2

Plans, subscriptions, seats, usage metering (notably AI consumption), invoices,
payment provider integration, entitlements.

AI usage metering is Phase 1 even though billing itself is Phase 2 — margin
protection (G5) requires measurement before monetization.

### C15 — Platform Administration · Phase 1

Internal operations: tenant lifecycle, feature flags, system health,
just-in-time support access, platform-level audit.

Small but Phase 1: operating a multi-tenant platform without controlled support
access forces the exact ambient-access anti-pattern rejected in D-10.

---

## Context Relationships

Primary artifact flow — each arrow is a `derives_from` edge in the graph:

```
Discovery ──▶ Requirements ──▶ Design ──▶ Planning ──▶ Execution ──▶ Operations
                   │              │           │            │
                   │              ▼           │            ▼
                   │          Estimation ─────┘         Quality
                   │              │                        │
                   └──────────────▼                        │
                             Commercial ◀──────────────────┘
```

- **Identity & Tenancy** is upstream of everything (isolation boundary).
- **AI Orchestration** serves every context; depends on none of their internals.
- **Delivery Graph** is the substrate all artifact-producing contexts write to.
- **Analytics** is downstream of all, read-only, via events and read models.

**Coupling rule:** upstream contexts must not know their consumers. Requirements
does not call Planning; it emits `RequirementApproved`, and Planning subscribes.
This keeps the dependency graph acyclic and every context independently
testable.

---

## Functional Scope by Phase

### Phase 1 — Foundation and Requirements *(first revenue slice)*

**In:** tenant onboarding and identity; project workspace; discovery intake and
structured elicitation; AI-assisted BRD and SRS generation with human approval;
requirement versioning and traceability; the Delivery Graph kernel; AI
orchestration with prompt versioning, evals, guardrails and cost metering;
platform administration; audit logging.

**Rationale:** the shortest path to standalone value. An agency that only ever
uses discovery → BRD → SRS still gets a week of senior time back per
engagement. It also exercises the graph, the tenancy model, and the AI stack
end to end — the three highest-risk parts of the architecture — while the blast
radius is still small.

### Phase 2 — Design, Estimation and Commercial *(completes the pre-sales loop)*

**In:** architecture and data model design assistance; API contract design;
estimation with risk-adjusted ranges; timeline and cost modelling; proposal
generation; contract generation with e-signature; client stakeholder portal;
Git and issue-tracker integrations; billing.

**Rationale:** closes G1 (idea → priced proposal in 48h), the highest
willingness-to-pay moment in the customer's cycle.

### Phase 3 — Delivery Execution

**In:** work breakdown and task generation; sprint planning; developer
workspace with automatic context assembly; deep Git integration; QA workspace;
test case generation; defect management; analytics including estimate-vs-actual
calibration.

**Rationale:** closes the actuals loop that makes G2 achievable. Estimation
cannot calibrate until real delivery data flows back.

### Phase 4 — Operations and Enterprise Readiness

**In:** deployment and environment tracking; maintenance and support workflows;
AI project management (proactive risk detection); SSO/SCIM at enterprise grade;
data residency; SOC 2 Type II readiness; advanced ABAC.

### Explicitly Out of Scope

| Excluded | Reason |
| --- | --- |
| Writing and shipping application code for the client's project | A different product in a crowded, capital-intensive market. We orchestrate delivery; coding agents integrate as tools. |
| Hosting or running the client's delivered software | Would make us a PaaS. Unrelated cost structure, compliance surface, and expertise. |
| Time tracking and invoicing of the agency's own staff | Commodity, well-served, integration-appropriate. |
| CRM / lead management | Adjacent but a distinct product; integrate instead. |
| Non-software project delivery | Domain model is software-specific by design; generalizing dilutes every module. |
| On-premise / air-gapped deployment | Incompatible with the hosted AI architecture at this stage. Revisit only with a contract that funds it. |

Exclusions are stated because **an unstated exclusion is an implied
commitment**. Each of the above will be requested by a prospect; the answer is
decided here, once, rather than improvised per deal.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-12 | Modules are bounded contexts by capability, not technical layers | Layer-based modules produce a distributed ball of mud |
| D-13 | Delivery Graph is a small shared kernel with no business rules | Traceability is inherently cross-context; keeping it thin bounds the coupling |
| D-14 | AI Orchestration is a context, not a library in each module | Central cost control, consistent guardrails, evaluable quality |
| D-15 | Discovery separate from Requirements | Prevents unvalidated assumptions being laundered into signed commitments |
| D-16 | Analytics reads only via events and published read models | Prevents analytics becoming the coupling that defeats modularity |
| D-17 | Cross-context flow is event-driven; upstream never knows consumers | Keeps the dependency graph acyclic and contexts independently testable |
| D-18 | Phase 1 ships the requirements slice, not a thin version of everything | Standalone value, and exercises the three riskiest subsystems early |
| D-19 | AI cost metering in Phase 1, billing in Phase 2 | Cannot protect margin (G5) without measuring first |
| D-20 | Evaluate `metrial-auth` for C1 before building identity | Removes months from the critical path on the riskiest security surface; requires an ADR, not an assumption |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Boundaries erode under delivery pressure | Modular monolith degrades to a big ball of mud; extraction becomes impossible | Mechanical enforcement in CI, not documentation and good intentions |
| Delivery Graph kernel accumulates business logic | Becomes a god module and a bottleneck | ADR required for any kernel change; explicit "no business rules" rule |
| 15 contexts is too many for an early team | Overhead exceeds benefit; thin, unfinished modules everywhere | Only 5 are Phase 1; the rest are named boundaries, not code to write now |
| Phase 1 scope proves too narrow to sell | Delayed revenue | Validate the requirements-only slice with design partners before Phase 2 lock |
| ~~`metrial-auth` evaluation assumed rather than performed, and its tenancy model conflicts~~ — **resolved**: evaluation performed, package unreachable, ADR-0001 builds Identity in-house | N/A | See `docs/architecture/adr/0001-metrial-auth-evaluation-outcome.md` |

## Dependencies

- **Depends on:** vision and goals (`01`), personas (`02`).
- **Depended on by:** high-level architecture (module boundaries become code
  boundaries), multi-tenancy, folder strategy, roadmap.

## Future Improvements

- Publish per-context glossaries; where the same word means different things in
  two contexts, that difference is the boundary and should be documented.
- Define the event catalogue (name, payload contract, producer, consumers)
  before the first cross-context event ships.
- Re-examine whether Estimation and Planning should merge after Phase 3 real
  usage; they are adjacent enough that the split may prove artificial.
