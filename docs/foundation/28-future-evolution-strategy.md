# Future Evolution Strategy

## Purpose

Define how the platform stays maintainable for ten years. This is the capstone of
the engineering foundation: every preceding document optimizes some dimension of
quality; this one addresses the dimension that subsumes them — whether the system
can still be changed safely by people who did not build it, long after the
current stack, team and market assumptions have all been replaced.

## Scope

**In scope:** what ten-year maintainability actually means and how it is
measured, predicted change and its cadence, the reversibility ledger, technology
refresh, replacement patterns, drift detection, knowledge continuity, and the
conditions under which this foundation is itself replaced.

**Out of scope:** the product roadmap (`20`), which sequences what gets built;
this document governs how what gets built stays changeable.

---

## What Ten-Year Maintainability Means

**It is not stability.** A system unchanged for ten years is not maintained; it
is abandoned, and usually unmaintainable — the knowledge of how to change it left
with the people who last did.

**It is not the absence of legacy code.** All code becomes legacy. The question is
whether legacy code can be safely modified, not whether it exists.

**The operational definition:** *an engineer who joins in year eight can make a
correct, safe change to a system built in year one, in their first week, without
consulting anyone who was there.*

That definition is deliberately demanding and it is measurable — M-5 (new
engineer to first merged PR in under three days) is its Phase 2 proxy. It also
identifies what actually has to be true:

| Requirement | Provided by |
| --- | --- |
| The structure is predictable | Uniform module layout (`11`), enforced boundaries (M-2) |
| The reasoning is available | ADRs, this foundation set, comments explaining *why* (D-135) |
| Change is verifiable | Test suite that catches breakage (`14`) |
| Mistakes are contained | Fail-closed mechanisms, gates, reversible deploys |
| The vocabulary is consistent | Ubiquitous language (`25`) |
| Nothing depends on undocumented knowledge | Bus-factor discipline (below) |

**The observation that shapes everything else: the primary ten-year risk is
people leaving, not technology aging.** Technology decay is visible, budgetable
and solvable with money. Knowledge decay is invisible until the moment someone
needs it and it is gone — and no amount of money recovers it.

---

## What Will Change, and When

Planning for evolution requires being explicit about what we expect to churn.
From `23`, with the implication for each:

| Layer | Expected churn | Implication |
| --- | --- | --- |
| **AI layer** | 1–3 years | Designed to be discarded (D-42). Do not over-invest in its internals. |
| **Frontend framework** | 3–5 years | Keep business logic out of components; the API contract is the stable boundary |
| **Backend framework** | 3–7 years | Domain must be insulated (D-130) — this is the decision that makes it survivable |
| **Infrastructure** | 2–5 years | Depend on contracts (S3 API, OTel, containers), not vendor primitives |
| **Module boundaries** | 5–10 years | Movable, at bounded cost, because coupling is constrained (D-222) |
| **Public API contracts** | 5–10 years | Deprecation is slow; get the shape right (`27`) |
| **Event schemas** | Effectively permanent | Persisted and replayed; additive-only forever (D-275) |
| **Database schema** | 10+ years | Data outlives every application that touches it |
| **Domain model and vocabulary** | 10+ years | The most valuable and most expensive thing to get wrong |
| **This foundation's principles** | 10+ years | Reviewed, refined, rarely reversed |

**The strategic consequence, stated once because it governs a decade of effort
allocation: invest care in inverse proportion to expected churn.** An hour spent
on the domain model or the schema compounds for ten years. An hour spent
perfecting the current AI orchestration will be thrown away within two. Teams
routinely invert this, because the fast-moving layer is the interesting one.

---

## The Reversibility Ledger

**Decision.** We maintain a living record of every high-reversal-cost decision:
what was assumed, what would force a revisit, and what the exit path is.

**Reasoning.** `23` classifies decisions by reversal cost, but a classification
made once and never revisited is worthless — the whole point is that
circumstances change. Three specific failures occur without a ledger:

1. **Assumptions are forgotten.** A decision was correct given what we believed;
   nobody records the belief, so nobody notices when it stops being true.
2. **Triggers are never monitored.** `08` defines sharding triggers and `24`
   defines sunset triggers, but a trigger nobody watches is a trigger that fires
   after the wall is hit.
3. **Exit paths are never designed.** By the time a decision must be reversed,
   the exit has to be invented under pressure, which is when it is done worst.

**Ledger entry format:**

| Field | Content |
| --- | --- |
| Decision | The `D-nn` or ADR reference |
| Reversal class | One-way / heavy / two-way (`23`) |
| Assumption | What must remain true for this to stay correct |
| Trigger | The observable that says revisit now |
| Owner | Who watches the trigger |
| Exit path | How we would actually undo it, at what approximate cost |
| Last reviewed | Date |

**Initial entries** — the decisions whose reversal would be most expensive:

| Decision | Assumption | Trigger | Exit path |
| --- | --- | --- | --- |
| D-43 Postgres as sole system of record | Relational + JSONB + vectors + graph traversal remain adequate | P-3 breached; write throughput > 70% (TR-1, TR-2) | Derived read models first; dedicated store for one concern; sharding last |
| D-54 RLS as the isolation mechanism | RLS overhead stays within P-1; Postgres remains the store | Latency regression traced to policy evaluation | Application-level scoping as primary with RLS retained as backstop — strictly worse, hence one-way in practice |
| D-40 Laravel for the core | Team fluency and `metrial-auth` reuse hold | Hiring failure; framework abandonment; identity reuse fails (TR-3) | Domain layer is framework-free (D-130); rewrite is Presentation + Infrastructure only |
| D-102 Monorepo | One team; access control needs stay repository-wide | External contributors need partial access | Subtree split preserving history — deliberately the cheaper direction (`11`) |
| D-29 Modular monolith | Deploy coupling remains acceptable; boundaries hold | Independent scaling or deploy isolation genuinely required | Extract along an existing seam with an ADR (D-39) |
| D-51 Claude as default model | Quality, price and terms remain competitive | Provider degradation; pricing shift; terms change (TR-4) | Provider abstraction; evaluated fallbacks already exercised |
| D-268 URI API versioning | v1 shape remains extensible additively | A genuinely breaking need arises | v2 alongside v1; deprecation window (`27`) |

**Alternatives.** *ADRs alone* — they record the decision and its context well,
and they are point-in-time documents nobody re-reads, so the assumption is
captured but never re-examined. *Risk register alone* — tracks what might go
wrong, not which decisions depend on what remaining true. *Periodic
architecture review with no artifact* — depends entirely on whoever attends
remembering the original assumptions, which is the failure this exists to
prevent.

**Trade-offs.** The ledger is maintenance work, and it will occasionally record
assumptions that never mattered. Cheap relative to discovering a violated
assumption by hitting it.

**Benefits.** Revisiting a decision becomes a scheduled, evidence-driven act
rather than a crisis response. The exit path is designed while there is time to
design it well. And a new engineer can see not just what was decided but what
would change our minds — which is the most useful thing to know about someone
else's decision.

**Long-term impact.** This is the single most concrete artifact for ten-year
maintainability, because it converts "we should revisit this someday" into an
owned, monitored commitment. Reviewed at every phase boundary alongside the risk
register.

---

## Technology Refresh

**Decision.** Continuous small upgrades, never big-bang modernization projects.

**Reasoning.** Covered as cadence in `24` (D-239); the evolution-level point is
different and worth stating separately: **a modernization project is a symptom of
failed continuous maintenance, not a solution to it.** It is unbudgeted, delivers
no customer-visible value, is therefore perpetually deprioritized, and grows more
expensive the longer it waits. Teams that stay current never need one.

The same logic applies beyond dependency versions:

| Refresh type | Cadence | Mechanism |
| --- | --- | --- |
| Dependency patch/minor | Continuous, automated | Automated updates with a test gate |
| Dependency major | Within one cycle of release | Planned work in the 20% allocation |
| Language/runtime major | Within 6 months of stable | Planned work |
| Framework major | Within one cycle; assessed for effort | Planned; domain layer insulates the blast radius |
| Infrastructure | As managed services evolve | IaC change, staging-verified |
| Deprecated internal patterns | Opportunistically as code is touched | The boy-scout rule, bounded by P9 |

**Alternatives.** *Upgrade only when forced* — covered in `24`; the superlinear
trap. *Scheduled modernization projects* — the standard corporate answer, and it
fails for the reason above. *Freeze the stack deliberately* — legitimate for a
system in genuine maintenance mode with a known end date; incompatible with a
platform expected to evolve for a decade.

**Trade-offs.** A steady tax on capacity, forever, with no customer-visible
output. This is genuinely hard to defend in a planning meeting, which is exactly
why it is written down here as a standing commitment rather than argued
case-by-case.

**Benefits.** Upgrades stay small enough to diagnose. Security patches are
reachable. The team keeps the upgrade habit, which is most of the difficulty.

**Long-term impact.** The difference between a year-ten system that is current
and one that is three majors behind on everything and effectively frozen.

**On opportunistic migration:** code being modified for other reasons is migrated
to current patterns as it is touched, within the bounds of P9 (restructure
first, separately, then change). This is what prevents a codebase containing four
generations of patterns — the condition where new engineers cannot tell which one
is current.

---

## Replacing a Subsystem

**Decision.** Strangler fig by default; rewrite only under demonstrated
necessity.

**Reasoning.** A rewrite discards embedded knowledge — years of bug fixes,
edge-case handling and hard-won behaviour that nobody documented because nobody
knew it needed documenting. It also requires the new system to reach parity
before delivering any value, which means a long period of pure cost and high risk
of abandonment midway. The result is frequently a system with the original's
problems plus new ones.

**Strangler process:**

1. Establish a boundary in front of the subsystem — a facade or interface.
2. Build the replacement behind the same boundary.
3. Route a slice of traffic to the new path, behind a flag.
4. Compare behaviour on real traffic — shadow reads where the operation is safe
   to duplicate.
5. Increase the routed share as confidence grows.
6. Delete the old path when traffic reaches zero (P13).

**Benefits.** Bounded, reversible risk at every step. The system works
throughout. Value arrives incrementally rather than at the end.

**Trade-offs.** Slower than a rewrite would be *if a rewrite went well*, which is
the assumption that usually fails. Requires maintaining both paths temporarily,
and the facade may outlive its usefulness and need removing afterwards.

**When a rewrite is nonetheless justified** — both conditions, demonstrated:

1. The existing system cannot meet a requirement it must meet, and
2. Incremental change cannot get there.

"The code is bad," "the framework is old," and "nobody understands it" are
refactoring, upgrade and documentation problems respectively. None is a rewrite
justification, and treating them as one is the most reliable way to lose a year.

---

## Architectural Drift Detection

Drift is the gap between intended and actual architecture. It is always positive
and always growing; the question is whether it is measured.

| Signal | Detection | Response |
| --- | --- | --- |
| Boundary violations | Fitness functions in CI (M-2) | Build fails — drift cannot accumulate |
| Suppression growth | Suppression count tracked as a defect trend (D-128) | Investigate; a rising count means the rule or the code is wrong |
| Coupling increase | Periodic dependency analysis | Review at phase boundaries |
| Test suite decay | Flake rate, duration, coverage on domain paths | Suite health metrics (`14`) |
| CI duration growth | Pipeline duration metric (M-4) | Treated as compounding debt |
| Documentation staleness | Phase-boundary review; docs changed in the same PR (D-181) | Update or delete |
| Pattern divergence | Review observation; recurring misplacement comments | Strengthen the fitness function or the guidance |
| Bypass frequency | Tracked (D-171) | Frequent bypass means the gates or the process is wrong |

**The suppression count is the most informative single metric.** Fitness
functions cannot be violated while they hold, so drift shows up first as pressure
to suppress them. A rising suppression count is the earliest available signal
that architecture and reality are separating.

---

## Knowledge Continuity

The hardest ten-year problem, and the one least addressed by technical practice.

**Decision.** No system, decision or capability may depend on a single person's
undocumented knowledge.

**Reasoning.** Over ten years, complete turnover is the realistic assumption, not
the pessimistic one. Every piece of knowledge existing only in someone's head is
a countdown. Unlike technical debt, this debt cannot be repaid after the fact —
when the person leaves, the knowledge is simply gone, and the only recovery is
re-derivation from the code, which is slow and lossy.

**Alternatives.** *Documentation as the sole mechanism* — necessary and
insufficient; documentation captures what someone thought to write down, and the
knowledge that matters most is usually the knowledge nobody realized was
special. *Long handover periods* — helps, and depends on the departure being
planned, which many are not. *Accept the loss and re-derive from code* — the
default outcome; slow, lossy, and it silently discards the reasoning behind
every decision, which is not recoverable from code at all.

**Trade-offs.** Every mechanism below costs time that produces no immediate
feature. Bus-factor remediation in particular means deliberately assigning work
to the person who will be *slower* at it, which is hard to justify in any single
sprint and correct across a decade.

**Benefits.** Departures become disruptive rather than catastrophic. And the
same mechanisms that preserve knowledge across departures also transfer it to
new joiners, so the investment pays continuously rather than only at the moment
of loss.

**Mechanisms:**

| Mechanism | Preserves |
| --- | --- |
| **ADRs** | Why a decision was made, and what was rejected — irrecoverable from code |
| **This foundation set** | The reasoning behind the system's shape |
| **Tests as specification** | Intended behaviour, executably |
| **Explicit code (P5)** | Structure and dependency, without tribal context |
| **Ubiquitous language (`25`)** | Domain meaning |
| **Runbooks** | Operational knowledge, verified by drills |
| **Onboarding verified by the next joiner (D-185)** | Catches decay invisible to incumbents |
| **Bus-factor review** | Identifies single points of knowledge before they leave |

**Bus-factor review** is explicit, not implicit: at each phase boundary, identify
any area only one person can safely change, and fix it — pairing, documentation,
or deliberate cross-assignment of the next change in that area.

**The counter-intuitive part:** the strongest knowledge-continuity mechanism is
not documentation but **constraint**. A codebase where the structure is enforced,
the vocabulary is consistent and the patterns are uniform requires far less
transferred knowledge in the first place. Documentation explains exceptions;
constraint removes the need for them.

---

## Evolving the Product Itself

Features also need lifecycle management, and this is almost universally
neglected.

**Decision.** Product capabilities are sunset with the same discipline as
technology (`24`) and APIs (`27`).

**Reasoning.** Every retained feature costs maintenance, testing, documentation,
support surface, and constraint on adjacent design. Products accumulate features
monotonically because removal is politically harder than addition — and after ten
years, most of the maintenance burden is features few customers use.

**Sunset process:** measure usage per feature; identify low-usage/high-cost
candidates; announce with the migration path; deprecate in-product; remove and
delete everything associated (P13).

**Requirement:** per-feature usage telemetry from Phase 1. Without it, sunset
decisions are opinion, and opinion loses to whoever advocates loudest for
keeping things.

---

## The AI Layer's Special Status

**Decision.** The AI layer is explicitly designed to be replaced, and is exempted
from the durability expectations applied elsewhere.

**Reasoning.** Model capability, tooling and best practice move on a 6–18 month
cycle. Much of our current workflow scaffolding — retry logic, output coercion,
context assembly heuristics, some guardrails — exists to compensate for present
model limitations and will be unnecessary as those limitations recede. Building
it to last a decade would be building the wrong thing carefully.

**What this permits:** faster iteration, less abstraction, acceptance of
solutions we know are temporary, and pragmatism about internal quality inside
the AI service.

**What it does not permit — the boundary matters more than the exemption:**

- The **interface** between the platform and the AI service is durable and
  versioned normally.
- **Tenant scoping in retrieval** (T-7) is a Tier 1 concern, never relaxed.
- **Lineage recording** is permanent — generated artifacts must remain
  explicable in ten years, long after the workflow that produced them is gone.
- **Evaluation discipline** applies fully; the exemption is about
  implementation durability, not quality.

**Long-term impact.** This is the deliberate application of D-229 — the layer
expected to churn fastest gets the least durability investment, and the boundary
around it gets the most. The moat is the graph, not the workflows (TR-6).

---

## When This Foundation Is Replaced

Recorded because a foundation without exit criteria becomes doctrine.

**Amend** (normal, expected): a decision proves wrong on evidence; an ADR
supersedes it; the document is updated at a phase boundary.

**Rewrite a document:** its subject has changed structurally — a new tenancy
model, a different architectural style, a fundamentally different AI approach.

**Rewrite the set:** the product's premise has changed. If the Delivery Graph bet
is invalidated (BR-1) and the company pivots, this foundation describes a system
we are no longer building, and continuing to reference it would be worse than
having none.

**The signal that it has been abandoned rather than replaced:** engineers stop
citing decision IDs in review and ADRs stop being written. That is a symptom of
the foundation having lost authority, and the correct response is to find out why
— usually because it has drifted from what the team actually does, which means
either the documents or the practice is wrong and both are fixable.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-283 | Maintainability defined as "a year-eight joiner can safely change year-one code in week one" | Operational and measurable; stability and absence of legacy are the wrong definitions |
| D-284 | The primary ten-year risk is knowledge loss, not technology aging | Technology decay is visible and budgetable; knowledge decay is invisible and unrecoverable |
| D-285 | Care invested in inverse proportion to expected churn | Schema and domain compound for a decade; the AI layer will be discarded |
| D-286 | A reversibility ledger records assumption, trigger, owner and exit path for high-cost decisions | Converts "revisit someday" into an owned, monitored commitment |
| D-287 | Every deferred decision has a monitored trigger and a named owner | An unwatched trigger fires after the wall is hit |
| D-288 | Continuous refresh; modernization projects are treated as a symptom of failure | They are unbudgeted, unprioritized, and grow more expensive while waiting |
| D-289 | Opportunistic pattern migration as code is touched | Prevents a codebase containing four generations of patterns |
| D-290 | Strangler fig by default; rewrites require two demonstrated conditions | Rewrites discard embedded knowledge and reach value only at the end |
| D-291 | Suppression count is the primary drift signal | Fitness functions cannot be violated while they hold; drift appears first as pressure to suppress |
| D-292 | No capability may depend on one person's undocumented knowledge; bus-factor reviewed per phase | Knowledge debt cannot be repaid after the person leaves |
| D-293 | Constraint is a stronger continuity mechanism than documentation | Uniformity reduces the knowledge that must be transferred at all |
| D-294 | Product features are sunset with the same discipline as technology | Otherwise most year-ten maintenance serves features few customers use |
| D-295 | Per-feature usage telemetry from Phase 1 | Without data, sunset decisions are opinion, and opinion favours keeping everything |
| D-296 | The AI layer is exempt from durability expectations; its boundary is not | The layer that churns fastest gets least investment; the interface around it gets most |
| D-297 | This foundation has stated conditions for amendment, rewrite and replacement | A foundation without exit criteria becomes doctrine |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Reversibility ledger created and never reviewed | False confidence; triggers fire unnoticed | Reviewed at phase boundaries with the risk register; each entry has an owner |
| Continuous refresh deprioritized under delivery pressure | The superlinear upgrade trap; legacy system by year five | Part of the standing 20% allocation (D-124); version currency tracked |
| Knowledge concentrates in founding engineers | Catastrophic loss on departure | Bus-factor review per phase; ADRs and tests as durable specification |
| Strangler facades outlive their purpose | Permanent indirection nobody understands | Facade removal is part of the migration's definition of done |
| Drift accumulates below the detection threshold | Architecture and reality separate silently | Suppression count and coupling analysis reviewed at phase boundaries |
| Feature sunset never happens for political reasons | Maintenance burden dominated by unused features | Usage telemetry makes the cost visible and the argument factual |
| AI-layer exemption used to justify low quality at its boundary | Isolation or lineage compromised | The exemption's boundaries are stated explicitly and are Tier 1 concerns |
| Foundation abandoned rather than amended | Decisions lose their rationale — the failure the product exists to fix | Citation of decision IDs and ADR frequency treated as health signals |

## Dependencies

- **Depends on:** the entire foundation set; especially architecture philosophy
  (`23`), technology selection (`24`), versioning (`27`).
- **Depended on by:** the long-term viability of everything else.

## Future Improvements

- Publish the reversibility ledger as a standalone living document once Phase 0
  validates or invalidates its initial entries.
- Add automated reporting for the drift signals that are measurable —
  suppression count, coupling metrics, dependency age.
- Add per-feature usage telemetry to the Phase 1 instrumentation requirements so
  the sunset process has data from the start.
- Re-assess the churn table with observed data at Phase 4; the current figures
  are informed estimates.
