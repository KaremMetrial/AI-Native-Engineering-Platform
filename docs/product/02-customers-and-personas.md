# Target Customers and User Personas

## Purpose

Define who the platform is built for, so that scope, permission models, and
interface priorities are driven by real users rather than by feature symmetry.
Personas here directly determine the role model in
`docs/architecture/07-multi-tenancy-strategy.md` and the module priorities in
`docs/product/03-core-modules-and-scope.md`.

## Scope

**In scope:** customer segments, buying dynamics, personas, their jobs to be
done, the artifacts each produces and consumes, and the access characteristics
each implies.

**Out of scope:** UX flows, wireframes, information architecture. Those belong
to product design once the foundation is approved.

---

## Customer Segments

### Primary — Software agencies and consultancies (20–200 engineers)

The beachhead. Their economics make the pain acute and measurable.

- Revenue depends on winning fixed-scope work at defensible prices.
- Unbilled pre-sales effort is pure cost, and most proposals lose.
- Estimate error transfers directly to margin, project by project.
- They run many concurrent projects for many clients, so per-project context
  loss is constant rather than occasional.

**Buying dynamic:** founder, delivery director, or CTO decides. Short cycle,
budget authority in the room, tolerant of a young product if the ROI story is
concrete. Security review is usually light — a questionnaire, not an audit.

### Secondary — Internal product teams at mid-market companies (Phase 3+)

Similar lifecycle needs, different economics: no proposals or contracts, but
strong demand for requirement traceability, architecture records, and planning.

**Buying dynamic:** engineering leadership, with procurement and an IT security
review. Requires SSO at minimum. This is why identity is architected for SSO
from Phase 1 even though the beachhead does not demand it.

### Tertiary — Enterprise IT and systems integrators (Phase 4+)

Largest contracts, longest cycles, hardest requirements: SSO/SCIM, granular
audit, data residency, SOC 2 Type II, sometimes single-tenant deployment.

**Deliberate position:** not a Phase 1 target, but the architecture must not
foreclose it. The isolation model supports promoting a tenant to a dedicated
schema or database without an application rewrite
(`docs/architecture/07-multi-tenancy-strategy.md`). We are paying a small
amount of design cost now to keep a large option open later — an explicit,
bounded bet, not speculative generality.

### Explicitly not targeted

| Segment | Why not |
| --- | --- |
| Solo freelancers | Cannot sustain per-seat pricing; lifecycle overhead exceeds their need |
| Non-software project delivery | Domain model is software-specific by design; generalizing would dilute every module |
| Regulated on-premise-only buyers (defense, some health/gov) | Air-gapped deployment is incompatible with a hosted AI architecture at this stage |

---

## User Personas

Each persona lists the artifacts they **produce** and **consume**, because
those flows define the Delivery Graph's edges and the permission model's
boundaries.

### P1 — Agency Founder / Delivery Director *(economic buyer)*

- **Goal:** win more work at better margin; know which projects are drifting
  before they are underwater.
- **Pain:** discovers cost overruns after they have already happened.
- **Produces:** commercial approvals, contract sign-off.
- **Consumes:** portfolio health, margin analytics, estimate-vs-actual trends.
- **Access implication:** cross-project visibility over the whole tenant,
  including commercial data most staff must not see. **Drives the need for
  attribute-based rules on financial fields, not just role-based ones.**

### P2 — Business Analyst / Pre-sales Consultant

- **Goal:** turn a vague client conversation into a rigorous BRD and SRS fast.
- **Pain:** repeatedly re-interviews clients for information already captured
  somewhere; requirements churn silently.
- **Produces:** discovery notes, BRD, SRS, requirement traceability.
- **Consumes:** client context, prior similar projects, reusable requirement
  patterns.
- **Access implication:** heaviest AI user in the product; primary driver of
  the per-tenant AI budget model. **The persona whose trust in AI output (G4)
  makes or breaks adoption.**

### P3 — Solution Architect

- **Goal:** produce an architecture, data model, and API design that satisfy the
  SRS and survive contact with delivery.
- **Pain:** architecture decisions live in someone's head or a stale diagram;
  the rationale is lost by the time it is questioned.
- **Produces:** architecture design, ADRs, data model, API contracts, NFRs.
- **Consumes:** SRS, constraints, prior architectures.
- **Access implication:** needs design authority scoped per project, and needs
  AI output that is *auditable* — a proposed design must carry its reasoning
  and the requirements it satisfies, or it cannot be defended in review.

### P4 — Technical Product Manager / Delivery Manager

- **Goal:** convert an approved scope into a plan that reflects real capacity,
  and keep it honest as reality intervenes.
- **Pain:** re-planning by hand every time scope changes; no link between plan
  and the commitment that was sold.
- **Produces:** WBS, sprint plans, task breakdown, status reporting.
- **Consumes:** SRS, estimates, team capacity, delivery telemetry.
- **Access implication:** the primary consumer of change-impact analysis —
  "what does this scope change cost?" is only answerable across the graph.

### P5 — Tech Lead

- **Goal:** ensure tasks are technically coherent and the team is unblocked.
- **Pain:** tasks arrive without enough context; forced to reconstruct intent.
- **Produces:** technical task refinement, reviews, technical decisions.
- **Consumes:** architecture, API contracts, task context, code integration state.
- **Access implication:** the bridge persona between planning and execution;
  where a broken graph link becomes immediately visible.

### P6 — Developer

- **Goal:** understand exactly what to build and why, then build it.
- **Pain:** ambiguous tickets; missing acceptance criteria; context scattered
  across five tools.
- **Produces:** implementation, commits, PRs, technical notes.
- **Consumes:** task context, acceptance criteria, architecture, API contracts.
- **Access implication:** highest-volume seat, so the seat most sensitive to
  per-user cost. Also the persona most likely to route around the platform if
  it adds friction — **the direct threat to G3.**

### P7 — QA Engineer

- **Goal:** verify the build matches what was specified.
- **Pain:** test cases derived from prose requirements, manually, repeatedly.
- **Produces:** test plans, test cases, defect reports, verification results.
- **Consumes:** SRS, acceptance criteria, build/deployment state.
- **Access implication:** closes the traceability loop — requirement → test →
  result. Without this persona the graph proves nothing about correctness.

### P8 — Client Stakeholder *(external)*

- **Goal:** understand what they are buying and confirm it is right, without
  learning a delivery tool.
- **Pain:** walls of technical documentation; approval by email thread.
- **Produces:** approvals, clarifications, change requests.
- **Consumes:** proposals, curated requirement summaries, progress views.
- **Access implication:** **the single most security-sensitive persona.**
  External identity, no seat licence, scoped to explicitly shared artifacts of
  one project, and never to tenant-wide data. Their existence is why the
  authorization model must be resource-scoped rather than purely role-based,
  and why sharing is an explicit, audited grant.

### P9 — Tenant Administrator

- **Goal:** manage members, roles, integrations, billing, and policy.
- **Produces:** role assignments, integration configuration, policy settings.
- **Consumes:** audit logs, usage and cost reporting, security posture.
- **Access implication:** highest privilege inside a tenant — and therefore the
  most valuable account to compromise. Requires step-up authentication for
  sensitive operations and complete audit coverage.

### P10 — Platform Operator *(internal, our staff)*

- **Goal:** keep the platform healthy; support tenants without violating them.
- **Produces:** operational actions, incident records.
- **Consumes:** system telemetry, aggregate metrics.
- **Access implication:** **must not have ambient access to tenant content.**
  Any tenant-data access is explicitly granted, time-bound, justified, and
  audited. Treating internal staff as trusted by default is a common and
  serious multi-tenant design failure; we reject it in the security strategy.

---

## Persona-to-Module Map

| Persona | Primary modules |
| --- | --- |
| P1 Founder | Analytics, Commercial, Identity |
| P2 Business Analyst | Discovery, Requirements, AI Orchestration |
| P3 Architect | Design, Requirements, AI Orchestration |
| P4 Delivery Manager | Estimation, Planning, Analytics |
| P5 Tech Lead | Planning, Execution, Design |
| P6 Developer | Execution, Integrations |
| P7 QA Engineer | Quality, Requirements |
| P8 Client Stakeholder | Commercial, Requirements (shared subset only) |
| P9 Tenant Admin | Identity & Tenancy, Billing, Platform Admin |
| P10 Platform Operator | Observability (no tenant content) |

Every core module has at least one owning persona. A module without an owner
would be a feature built for symmetry rather than need — the check that catches
speculative scope.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-07 | Beachhead is agencies of 20–200 engineers | Acute pain, direct budget authority, short sales cycle |
| D-08 | External client stakeholders are first-class but unlicensed | Approval is part of the lifecycle; charging for it would push it back to email and break the graph |
| D-09 | Authorization must be resource-scoped, not role-only | P8 and P1 cannot be expressed with roles alone |
| D-10 | Platform operators have zero ambient tenant-data access | Internal access is the most commonly overlooked breach path in SaaS |
| D-11 | Enterprise personas deferred, but isolation designed for them now | Tenancy rework is the most expensive possible retrofit |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Developer persona (P6) finds the platform slower than existing tools and routes around it | G3 fails; graph becomes incomplete and untrustworthy | Deep IDE/Git integration so context flows to the developer rather than demanding they come get it |
| Client stakeholder access mis-scoped, leaking one client's data to another | Severe breach, existential for an agency customer | Explicit per-resource grants, default deny, and cross-tenant isolation tests as a blocking CI gate |
| Persona list drifts from reality without design partner validation | Scope built for imagined users | Validate against 5–10 design partners before Phase 2 scope lock |
| Too many personas served too early | Shallow product across the board | Phase 1 serves P2, P3, P9 only; others are consumers of what those produce |

## Dependencies

- **Depends on:** `01-vision-mission-and-goals.md` for segment strategy.
- **Depended on by:** core modules and scope; multi-tenancy and authorization
  design; security strategy (external identity handling).

## Future Improvements

- Validate personas against design partner interviews; correct rather than
  confirm.
- Add per-persona onboarding paths once Phase 1 usage data shows where the
  drop-off actually is.
- Quantify seat mix per customer to test whether per-seat pricing survives
  contact with a developer-heavy tenant.
