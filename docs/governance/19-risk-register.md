# Risk Register

## Purpose

Consolidate the engineering, technical and business risks that could prevent
the platform from succeeding, with honest assessment and concrete mitigation.
Risks distributed across a dozen documents are risks nobody reviews as a set;
this is the single place leadership can see the whole picture.

## Scope

**In scope:** engineering, technical, AI-specific, operational and business
risks, with likelihood, impact, mitigation, early-warning signal, and the
decision each would force.

**Out of scope:** per-document risks in their local context (each foundation
document carries its own), and incident response (`15`).

---

## How to Read This

| Field | Meaning |
| --- | --- |
| **Likelihood** | Low / Medium / High — probability of materializing within the roadmap horizon |
| **Impact** | Low / Medium / High / Critical — Critical means it can end the company |
| **Signal** | The observable that tells us it is materializing, ideally before it does |
| **Forced decision** | What we would have to decide if it materializes |

**A risk without a signal is a risk we will discover too late.** Every entry
below names one; where a signal is weak, that weakness is stated rather than
disguised.

---

## Critical Risks

Ranked first because they are existential. Everything else is recoverable.

### CR-1 — Cross-tenant data leak

| | |
| --- | --- |
| **Likelihood** | Low |
| **Impact** | Critical |
| **Why critical** | Our customers hold their clients' data under NDA. A leak makes *our customer* the party in breach. Recovery from this reputationally is unlikely in a market where agencies talk to each other. |
| **Mitigation** | Six-layer enforcement (`07`); RLS as database-level backstop; isolation suite blocking merge (T-1); retrieval filtered before ranking (T-7); annual pentest |
| **Signal** | Isolation test failures; authorization denial anomalies; any customer report of unfamiliar data |
| **Forced decision** | Immediate disclosure, full audit, likely offer of dedicated infrastructure to affected customers |

**Residual risk after mitigation is low but not zero.** The layered model
protects against implementation mistakes; it does not protect against a novel
access path nobody modelled. This is why the isolation suite must grow with
every new data access pattern rather than being written once.

### CR-2 — AI output quality below the usefulness threshold

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Critical |
| **Why critical** | If reviewing AI output costs more than writing from scratch, the product has negative value. G4 fails and the core premise collapses. |
| **Mitigation** | Grounding in the tenant graph (`10`); evaluation from Phase 1; frontier models for synthesis; human-in-the-loop; narrow AI surface until thresholds are met |
| **Signal** | G4 approval rate below 70%; rising edit distance; regeneration rate |
| **Forced decision** | Narrow AI scope to the workflows that do meet the bar; reposition value around traceability rather than generation |

**Honest assessment:** this is the most likely of the critical risks. Document
synthesis from incomplete discovery input is genuinely hard, and the failure is
not obvious — plausible-sounding output that is subtly wrong is worse than
output that is obviously wrong. The mitigation that matters most is uncertainty
surfacing (D-99), because it converts silent error into visible gaps.

### CR-3 — Unit economics inverted by AI cost

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Critical |
| **Why critical** | Negative gross margin on the most engaged customers means growth destroys the company. |
| **Mitigation** | Per-tenant metering from Phase 1 (D-101); tier routing; caching; context discipline; spend caps in Phase 2 |
| **Signal** | Cost per artifact rising; margin per tenant falling; heaviest users least profitable |
| **Forced decision** | Usage-based pricing, tier restrictions, or aggressive model downgrading with quality consequences |

---

## Engineering Risks

### ER-1 — Module boundaries erode

| | |
| --- | --- |
| **Likelihood** | High without enforcement; Low with it |
| **Impact** | High — the modular monolith becomes a ball of mud and extraction becomes impossible |
| **Mitigation** | Mechanical enforcement in CI (M-2); boundary violations block merge |
| **Signal** | Rising boundary suppressions; cross-module imports in review |
| **Forced decision** | Dedicated refactoring effort, or accept the monolith is permanent |

**Likelihood is "High without enforcement" deliberately.** Every team believes
discipline will hold. It never does under deadline pressure. The enforcement is
the mitigation; the intention is not.

### ER-2 — CI pipeline becomes too slow

| | |
| --- | --- |
| **Likelihood** | High |
| **Impact** | Medium, compounding |
| **Why it compounds** | Slow CI causes batching → larger PRs → worse review → more defects → more tests → slower CI |
| **Mitigation** | Affected-target detection (D-104); parallelization; duration tracked as a metric (M-4) |
| **Signal** | Pipeline duration trending toward 10 minutes |
| **Forced decision** | Invest in build infrastructure; split the test suite by risk tier |

Near-certain over time, which is why it is treated as compounding debt with
standing priority (D-124).

### ER-3 — Small team cannot sustain the quality bar

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | High |
| **Detail** | The charter's standards, two-reviewer requirements and full gate suite assume more capacity than a very small team has. The two-reviewer rule in particular is impractical below about four engineers. |
| **Mitigation** | Maximize automation so gates carry the mechanical load; phase scope aggressively; be explicit about which manual practices are deferred and why |
| **Signal** | Review latency rising; bypass frequency rising; PR size growing |
| **Forced decision** | Hire, reduce scope, or formally relax specific manual practices — **stated openly rather than allowed to erode silently** |

**This is the most under-acknowledged risk in the whole foundation.** The
standards documented here are correct for the platform being built; whether they
are affordable depends entirely on team size. Silent erosion is the failure
mode; explicit, recorded relaxation is the honest alternative.

### ER-4 — Two backend languages fragment the team

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Medium |
| **Mitigation** | Keep the Python surface small and well-bounded; shared standards; no dedicated AI team (`12`) |
| **Signal** | Only one or two engineers able to work on the AI service |
| **Forced decision** | Cross-train, hire, or consolidate |

### ER-5 — AI-assisted code passes gates but is architecturally wrong

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Medium, compounding |
| **Detail** | Models reproduce common patterns — often framework-idiomatic ones that violate our deliberate layering (D-106). These pass linting and tests while eroding the architecture. |
| **Mitigation** | Architecture tests catch dependency violations; review focuses explicitly on architectural fit (D-136) |
| **Signal** | Review comments repeatedly flagging the same misplacement |
| **Forced decision** | Strengthen architecture tests; improve `CLAUDE.md` guidance |

---

## Technical Risks

### TR-1 — Postgres becomes the scaling ceiling earlier than expected

| | |
| --- | --- |
| **Likelihood** | Low within the roadmap horizon |
| **Impact** | High |
| **Mitigation** | Staged strategy with pre-defined triggers (`08`); tenant-partitionable design from day one |
| **Signal** | Write throughput above 70% of primary capacity |
| **Forced decision** | Shard, or promote large tenants to dedicated databases |

### TR-2 — Graph traversal performance fails P-3

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Medium |
| **Detail** | Relational traversal is slower than a native graph database. If impact analysis becomes slow at realistic depth, the platform's most differentiating feature feels broken. |
| **Mitigation** | Depth limits; tenant scoping; materialized closure tables; benchmark early |
| **Signal** | Traversal p95 approaching the P-3 budget |
| **Forced decision** | Materialized paths, a derived graph read model, or a dedicated graph store |

**Untested assumption, explicitly flagged.** The benchmark should happen in
Phase 1, not when a customer complains.

### TR-3 — `metrial-auth` incompatible with our tenancy model

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | High |
| **Detail** | Reuse is a primary justification for the Laravel choice (`06`). If its tenancy model conflicts with RLS-based isolation, we lose months and the stack rationale weakens materially. |
| **Mitigation** | Phase 0 evaluation spike with a written ADR **before** any identity code |
| **Signal** | The spike itself |
| **Forced decision** | Build identity ourselves, adapt the package, or reconsider the stack |

**The riskiest unvalidated assumption in the foundation**, and deliberately
placed first in the roadmap.

### TR-4 — Provider dependency (pricing, availability, terms)

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Medium |
| **Mitigation** | Provider abstraction (D-51); evaluated fallbacks; graceful degradation (A-5) |
| **Signal** | Provider pricing announcements; rising error or rate-limit rates |
| **Forced decision** | Switch providers, or self-host for high-volume tasks |

### TR-5 — Prompt injection succeeds despite controls

| | |
| --- | --- |
| **Likelihood** | Medium (attempts near-certain; success bounded) |
| **Impact** | Medium — bounded by architecture, not eliminated |
| **Mitigation** | Architectural containment (D-80, D-81); scope enforced outside the model; human approval gates; adversarial suite |
| **Signal** | Adversarial suite failures; anomalous tool-use patterns |
| **Forced decision** | Narrow tool access further; add content pre-screening |

**Honest position:** injection is not fully solvable with current model
technology. We contain rather than prevent, and the containment is architectural
so it holds even when a specific defence fails.

### TR-6 — Model advancement obsoletes the AI layer

| | |
| --- | --- |
| **Likelihood** | High |
| **Impact** | Low — by design |
| **Detail** | Capability advances will make some of our workflow scaffolding unnecessary. This is expected and largely welcome. |
| **Mitigation** | AI service is deliberately the most replaceable component (D-42); the graph, not the workflows, is the moat |
| **Forced decision** | Rewrite workflows — a bounded, planned-for exercise |

---

## Operational Risks

### OR-1 — Security incident from a dependency

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | High |
| **Mitigation** | SCA blocking on critical/high; 72-hour patch SLA; lockfiles; dependency review; minimal base images |
| **Signal** | Scanner alerts; advisory feeds |
| **Forced decision** | Emergency patch and deploy |

### OR-2 — Data loss from an untested restore procedure

| | |
| --- | --- |
| **Likelihood** | Low |
| **Impact** | Critical |
| **Mitigation** | Quarterly restore drills with recorded results (D-161); PITR; cross-region replication |
| **Signal** | Failed drill — which is the entire point of drilling |
| **Forced decision** | Rebuild the backup strategy |

### OR-3 — Alert fatigue masks a real incident

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Medium |
| **Mitigation** | Burn-rate alerting (D-158); runbook requirement (D-159); periodic alert review |
| **Signal** | Alert volume rising; alerts routinely acknowledged without action |
| **Forced decision** | Aggressive alert pruning |

### OR-4 — Noisy neighbour degrades service for small tenants

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Medium |
| **Mitigation** | Per-tenant limits, quotas and queue fairness (D-62); usage telemetry |
| **Signal** | Any tenant exceeding 5% of platform load (S-9); queue age spikes |
| **Forced decision** | Tighter quotas, or dedicated resources for large tenants |

---

## Business Risks

### BR-1 — Users route around the graph

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Critical |
| **Detail** | If teams work in documents and chat instead of maintaining links, the graph is incomplete, traceability is fiction, and the moat does not exist. The product degrades into a document generator — the commodity outcome we are explicitly avoiding. |
| **Mitigation** | G3 measured from Phase 1; links created automatically as a by-product of workflows rather than as manual data entry; developer-facing integration so context flows to people rather than demanding they come get it |
| **Signal** | G3 below 90%; low engagement with impact analysis |
| **Forced decision** | Re-examine UX fundamentally before building further modules |

**The most important business risk**, because it invalidates the strategic bet
in `01` rather than merely slowing it. The mitigation that matters is that
**link creation must be a by-product of doing the work**, never a separate
chore — users will not maintain a graph manually, and expecting them to is the
design error that would cause this.

### BR-2 — Cold-start problem

| | |
| --- | --- |
| **Likelihood** | High |
| **Impact** | High |
| **Detail** | The platform is most valuable once a tenant has invested substantial project data. Early value is therefore lowest exactly when the customer is deciding whether to continue. |
| **Mitigation** | Phase 1 delivers standalone value (BRD/SRS generation) independent of accumulated data; integrations ingest existing artifacts (`03`, C12 scheduled early); templates and patterns provide initial grounding |
| **Signal** | Trial-to-paid conversion; early churn |
| **Forced decision** | Invest further in import and migration tooling; consider guided onboarding as a service |

### BR-3 — Beachhead too small

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | High |
| **Mitigation** | Expansion path to internal product teams designed in; architecture does not preclude enterprise |
| **Signal** | Pipeline saturation in the agency segment |
| **Forced decision** | Accelerate the expansion to internal teams |

### BR-4 — Competitor ships a "good enough" alternative faster

| | |
| --- | --- |
| **Likelihood** | High |
| **Impact** | Medium |
| **Detail** | A document generator ships in weeks; our graph-backed approach takes quarters. A competitor will get to market first. |
| **Mitigation** | Phase 1 ships a revenue slice early; the moat compounds with tenant data, so late entry is survivable if retention is strong |
| **Signal** | Competitive losses citing speed to value |
| **Forced decision** | Compete on depth and retention, not on feature parity — a race to match a shallower product is a race to become one |

### BR-5 — Enterprise requirements arrive before we are ready

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Medium |
| **Detail** | A large prospect demands SOC 2, residency or single-tenant deployment before Phase 4. |
| **Mitigation** | Isolation promotion path preserved (D-63); SOC 2 controls adopted early (D-86) |
| **Signal** | Enterprise inbound; security questionnaires |
| **Forced decision** | Accelerate the compliance roadmap, funded by the contract — or decline the deal honestly |

### BR-6 — Regulatory change on AI

| | |
| --- | --- |
| **Likelihood** | Medium |
| **Impact** | Medium |
| **Detail** | AI regulation is evolving. Requirements around transparency, human oversight and AI-generated content disclosure could impose obligations. |
| **Mitigation** | **Our architecture is already aligned** — human-in-the-loop by design (D-36), full provenance (U-4), audit trails, no training on tenant data (C-3) |
| **Signal** | Regulatory developments in target markets |
| **Forced decision** | Add disclosures or documentation — likely incremental given the existing design |

**Worth noting as an asset rather than only a risk:** the design decisions made
for product-quality reasons (human approval, provenance, traceability) happen to
be exactly what emerging AI regulation asks for. That alignment is not
accidental — it follows from treating AI output as something a human must be
able to defend.

---

## Risk Summary

| ID | Risk | Likelihood | Impact |
| --- | --- | --- | --- |
| CR-1 | Cross-tenant data leak | Low | Critical |
| CR-2 | AI quality below usefulness threshold | Medium | Critical |
| CR-3 | Unit economics inverted by AI cost | Medium | Critical |
| BR-1 | Users route around the graph | Medium | Critical |
| OR-2 | Data loss from untested restore | Low | Critical |
| ER-1 | Module boundaries erode | High without enforcement | High |
| ER-3 | Team too small for the quality bar | Medium | High |
| TR-1 | Postgres scaling ceiling | Low | High |
| TR-3 | `metrial-auth` incompatible | Medium | High |
| OR-1 | Dependency security incident | Medium | High |
| BR-2 | Cold-start problem | High | High |
| BR-3 | Beachhead too small | Medium | High |
| ER-2 | CI pipeline too slow | High | Medium (compounding) |
| ER-4 | Two languages fragment the team | Medium | Medium |
| ER-5 | AI code architecturally wrong | Medium | Medium (compounding) |
| TR-2 | Graph traversal fails P-3 | Medium | Medium |
| TR-4 | Provider dependency | Medium | Medium |
| TR-5 | Prompt injection succeeds | Medium | Medium |
| OR-3 | Alert fatigue | Medium | Medium |
| OR-4 | Noisy neighbour | Medium | Medium |
| BR-4 | Faster competitor | High | Medium |
| BR-5 | Early enterprise requirements | Medium | Medium |
| BR-6 | AI regulation | Medium | Medium |
| TR-6 | Model advancement obsoletes AI layer | High | Low |

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-191 | Every risk carries an early-warning signal | A risk without a signal is discovered too late |
| D-192 | Every risk names the decision it would force | Prevents improvised decisions under pressure |
| D-193 | Register reviewed at every phase boundary | Risk profiles change as the system and company change |
| D-194 | Team-capacity risk (ER-3) stated openly | Silent erosion of standards is the failure mode; explicit relaxation is honest |
| D-195 | `metrial-auth` evaluation is the first roadmap item | Highest-impact unvalidated assumption |

## Risks

Risks about the risk process itself:

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Register written once and never revisited | False comfort | Phase-boundary review (D-193) |
| Signals defined but never monitored | Risks materialize undetected | Signals wired into dashboards where measurable |
| New risks not added as the system evolves | Blind spots | Retrospectives and post-incident reviews feed the register |
| Likelihood and impact are subjective estimates | Mis-prioritization | Re-assessed with evidence as data arrives; treated as hypotheses |

## Dependencies

- **Depends on:** every foundation document — this consolidates their local
  risks.
- **Depended on by:** roadmap sequencing; final recommendations.

## Future Improvements

- Wire measurable signals into dashboards so the register is monitored rather
  than read.
- Assign an explicit owner per critical risk.
- Re-assess likelihood with evidence after Phase 1; current values are informed
  estimates, not measurements.
