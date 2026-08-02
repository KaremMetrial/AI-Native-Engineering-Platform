# Future Roadmap

## Purpose

Sequence the work so that risk is retired early, value ships before the full
vision is built, and no phase creates rework for the next. Phases are ordered by
**risk and dependency**, not by feature attractiveness.

## Scope

**In scope:** phase sequencing, objectives, deliverables, exit criteria, and the
reasoning behind the order.

**Out of scope:** calendar dates, resourcing, and detailed task breakdown —
those follow from team size and validated learning, neither of which is known
yet.

---

## Sequencing Principles

1. **Retire the riskiest assumptions first.** Validate what could invalidate the
   plan before building on it. Every phase is partly an experiment.
2. **Ship standalone value early.** Each phase must be sellable on its own, not
   merely a step toward something sellable. A phase that only makes sense as
   scaffolding is a phase that can be cut and take the investment with it.
3. **Never build something a later phase must undo.** Tenancy, isolation and
   traceability are done properly from the start because retrofitting them is a
   rewrite.
4. **Defer what can be deferred cheaply.** Sharding, multi-region, dedicated
   search and vector stores are all deferred with a preserved migration path.

**On dates:** phases carry no calendar commitments. Estimates before Phase 0
completes would be fiction, and stating fiction as a plan is how engineering
loses credibility with the business. Estimates become meaningful once team size
is fixed and Phase 0 has validated its assumptions.

---

## Phase 0 — Validate and Prepare

**Objective:** eliminate the unvalidated assumptions this foundation rests on,
before any product code depends on them.

**No product features. This phase produces knowledge and infrastructure.**

### Deliverables

| Item | Purpose |
| --- | --- |
| **`metrial-auth` evaluation spike + ADR-0001** | Resolves TR-3, the highest-impact unvalidated assumption. Determines whether identity is reused or built — a months-scale difference. |
| **RLS isolation proof of concept** | Proves the isolation model works, including the role-privilege configuration (D-55), before any schema depends on it |
| **Graph traversal benchmark** | Resolves TR-2 — validates that Postgres meets P-3 at realistic volume, while changing course is still cheap |
| Monorepo scaffold with all gates operational | Standards enforced from the first product commit, not retrofitted (D-127) |
| Architecture and isolation test harnesses | The tests exist before the code they govern |
| Local development environment, one command | M-5 foundation |
| CI/CD pipeline, staging and preview environments | Deployment path proven before it is needed urgently |
| Baseline observability | O-1 to O-3 wired before the first traffic |
| AI evaluation harness skeleton | Evaluation capability exists before the first workflow |

### Exit criteria

- Identity decision made and recorded in an ADR
- Isolation model proven, including a test that RLS actually blocks a bypass
- Graph traversal benchmarked against P-3 with a documented result
- A trivial change flows from commit to staging through all gates
- New engineer setup verified end to end

**Why this phase exists, and why skipping it is the most expensive available
mistake:** three foundational assumptions — the identity package, the isolation
mechanism, the graph performance — are currently unvalidated. Discovering a
problem with any of them after Phase 1 means rebuilding on top of committed
schema and shipped code. Phase 0 buys that information at the lowest price it
will ever cost.

---

## Phase 1 — Requirements Intelligence *(first revenue)*

**Objective:** an agency can take an idea to an approved BRD and SRS, and get a
week of senior time back per engagement.

**Business goal:** G1 partial, G3, G4 baseline.

### Deliverables

**Foundation:** identity and tenancy with RLS enforcement; project workspace;
Delivery Graph kernel (artifacts, versions, links, lineage); audit logging;
platform administration.

**Product:** discovery intake and structured elicitation; AI-assisted BRD
generation; AI-assisted SRS generation; requirement versioning and change
control; traceability matrix; approval workflows.

**AI:** orchestration service; prompt registry; model routing; tenant-scoped
retrieval; guardrails including injection defence; evaluation harness with
golden sets; per-tenant cost metering (D-101).

### Exit criteria

- Discovery → BRD → SRS → approval works end to end
- AI evaluation thresholds met for all shipped workflows
- Isolation suite covers all Phase 1 access patterns
- Cost per artifact measured and reconciled against provider invoices
- **At least three design partners using it on real engagements** — the only
  meaningful validation
- G4 baseline established; G3 measurable

**Why the requirements slice first:** it delivers standalone value (an agency
that uses nothing else still saves senior time), and it exercises the three
highest-risk subsystems — tenancy, the graph, and the AI stack — end to end
while the blast radius is small enough to correct.

**Deliberately excluded:** estimation, proposals, planning, execution. Building
a thin version of everything would validate nothing and ship value nowhere.

---

## Phase 2 — Commercial Loop *(completes the highest-value cycle)*

**Objective:** idea to defensible, priced proposal in under 48 hours.

**Business goal:** G1 fully, G5 foundation.

### Deliverables

**Design:** architecture design assistance; ADR capture; data model design; API
contract design.

**Estimation:** effort estimation with risk-adjusted ranges; cost and timeline
modelling; capacity modelling. *(Calibration from actuals requires Phase 3
data — the estimates here are model-driven, and this limitation is stated to
customers rather than hidden.)*

**Commercial:** proposal generation; pricing; contract generation; e-signature
integration; external stakeholder portal with grant-based access (D-60).

**Platform:** billing and subscription; per-tenant quotas and spend caps ($-4);
queue fairness (D-62); Git and issue-tracker integrations; WCAG 2.2 AA
conformance (U-1).

### Exit criteria

- G1 demonstrated: median engagement from discovery to proposal under 48 hours
- External stakeholder access security-reviewed and isolation-tested
- Load tests pass at Phase 2 targets (S-1 through S-5)
- Billing reconciles with metered usage
- Paying customers, not only design partners

**Why commercial before delivery execution:** this is where willingness to pay
is highest and the pain most acute. It also completes a coherent standalone
product — pre-sales — that an agency can adopt without changing how it runs
delivery, which dramatically lowers the adoption barrier.

---

## Phase 3 — Delivery Execution *(closes the learning loop)*

**Objective:** work planned, executed and verified on the platform, with actuals
flowing back to calibrate estimates.

**Business goal:** G2, G3 fully, G5.

### Deliverables

**Planning:** work breakdown; task generation from approved SRS; dependencies;
sprint planning; capacity allocation; re-planning on scope change; change impact
analysis.

**Execution:** developer workspace with automatic context assembly; deep Git
integration; branch and PR linkage; technical notes.

**Quality:** test planning; test case generation from acceptance criteria;
defect management; verification results closing the traceability loop.

**Analytics:** portfolio health; **estimate-vs-actual calibration**; velocity;
AI quality and cost reporting.

**Platform:** MFA enforced for privileged roles (SEC-6); data partitioning
(`08` stage 3) as volume requires.

### Exit criteria

- Full lifecycle traceable: business objective → requirement → design → task →
  commit → test → deployment
- G2 measurable: estimate-vs-actual MAPE tracked, trending toward 25%
- G3 above 90% on platform-managed projects
- Developer adoption verified — **the make-or-break signal for BR-1**
- Load tests pass at Phase 3 targets

**Why this phase is the strategic proof point:** it closes the loop that makes
G2 achievable and turns the Delivery Graph from a documentation structure into a
learning system. It is also where BR-1 is decided — if developers route around
the platform, the graph is incomplete and the moat is fiction. Developer
adoption is therefore an exit criterion, not a hoped-for outcome.

---

## Phase 4 — Operations and Enterprise Readiness

**Objective:** support the full lifecycle through production, and meet the
requirements that unlock enterprise contracts.

### Deliverables

**Operations:** environment and deployment tracking; release coordination;
incident and maintenance workflows; support workflows.

**AI project management:** proactive risk detection (scope drift, estimate
divergence, dependency risk) — genuinely valuable only now, because it depends
on the full lifecycle data the earlier phases produce.

**Enterprise:** SSO/SCIM at enterprise grade; advanced ABAC; data residency
(C-4) with regional deployment; SOC 2 Type II (C-5); dedicated-tenancy promotion
(T-6); enhanced availability targets (A-2).

**Scale:** sharding if triggered (`08` stage 4); dedicated vector store if
triggered; multi-region.

### Exit criteria

- SOC 2 Type II achieved
- Data residency operational
- Phase 4 scale targets verified under load
- Enterprise customers in production

---

## Phase 5 and Beyond — Directional

Not committed. Recorded so that near-term decisions do not foreclose them.

| Direction | Rationale |
| --- | --- |
| **Per-tenant calibration** | Learning each tenant's conventions and estimation patterns from their approval edits — the graph's compounding value made explicit |
| **Coding agent integration** | Not building a coding agent (`03` exclusion), but orchestrating them with full requirement context is a natural extension |
| **Marketplace of methodologies** | Templates, patterns and estimation models shared across tenants — with strict isolation of the underlying data |
| **Benchmarking** | Anonymized, aggregated cross-tenant insight ("your estimate variance vs. similar agencies") — commercially valuable, requires careful privacy design and explicit consent |
| **Self-hosted models** | For high-volume fast-tier tasks, once volume justifies GPU operations |

**Benchmarking is flagged as requiring careful design**: aggregating across
tenants is in tension with the isolation guarantees that constitute our security
posture. It would need genuine anonymization, explicit opt-in, and a security
review of its own. It is recorded as a direction, not a plan.

---

## Cross-Phase Continuous Work

Not phase-bound; ongoing from Phase 0:

- Security: dependency patching, scanning, annual pentest, quarterly access
  review
- Reliability: restore drills, incident reviews, SLO management
- AI quality: golden set growth from production failures (D-98), human
  calibration sampling
- Technical debt: ~20% capacity default (D-124)
- Documentation currency: same-PR updates, phase-boundary review

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-196 | Phase 0 validates assumptions before any product code | Three foundational assumptions are unvalidated; discovering problems later means rebuilding on committed schema |
| D-197 | Every phase must be sellable standalone | A phase that only makes sense as scaffolding can be cut, taking its investment with it |
| D-198 | Requirements slice before commercial or delivery | Standalone value, and exercises the three riskiest subsystems at small blast radius |
| D-199 | Commercial before delivery execution | Highest willingness to pay; adoptable without changing how delivery runs |
| D-200 | Design partners are a Phase 1 exit criterion | Real usage is the only meaningful validation |
| D-201 | Developer adoption is a Phase 3 exit criterion | The signal that decides BR-1, and therefore the strategic bet |
| D-202 | No calendar dates until Phase 0 completes | Estimates before validation are fiction, and stating fiction costs credibility |
| D-203 | Enterprise requirements deferred to Phase 4, with the path preserved | Avoids building for customers we do not have while keeping the option open |
| D-204 | Cross-tenant benchmarking recorded as a direction, not a plan | In tension with isolation guarantees; needs its own security design |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Phase 0 seen as delay and skipped | Foundational assumptions fail late, causing rework on committed schema | Bounded scope with concrete exit criteria; framed as risk retirement, not preparation |
| Phase 1 too narrow to sell | Delayed revenue | Validate the slice with design partners before committing Phase 2 scope |
| Phase 3 developer adoption fails | BR-1 materializes; strategic bet invalidated | Measured explicitly as an exit criterion, so failure is visible rather than assumed away |
| Enterprise demand arrives before Phase 4 | Deals lost or scope disrupted | Promotion path preserved (D-63); SOC 2 controls adopted early (D-86) |
| Phases stretch as scope creeps | Value delayed | Exit criteria are the scope definition; additions require explicit trade-offs |
| Competitor ships first (BR-4) | Market position | Phase 1 ships a revenue slice early; the moat compounds with retention |

## Dependencies

- **Depends on:** every foundation document; risk register (`19`) for
  sequencing.
- **Depended on by:** all planning and resourcing.

## Future Improvements

- Add calendar estimates once team size is fixed and Phase 0 has completed.
- Define measurable success metrics per phase beyond the exit criteria.
- Re-sequence after Phase 1 learning — this roadmap is a hypothesis like
  everything else here.
