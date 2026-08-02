# Architecture Philosophy

## Purpose

Define *how* architectural decisions are made, evaluated and revisited — the
method, not the current answers. `05` records the architecture we have chosen;
this document defines the discipline that will produce the next ten years of
architecture, most of which will be decided by people who have not yet joined.

## Scope

**In scope:** what architecture is for, the criteria decisions are judged
against, how boundaries are chosen, how complexity is governed, how architecture
is enforced and how it is allowed to change.

**Out of scope:** the current architecture (`05`), technology choices (`06`,
`24`), and the principles that govern engineering judgment generally (`22`).

---

## What Architecture Is

**Architecture is the set of decisions that are expensive to change.**

This definition is operational rather than academic, and it has a direct
consequence: **the primary job of architecture is to minimize the number of
decisions that fall into that set.** Not to make more of them, earlier, with
more confidence — to make fewer of them matter.

Everything follows from this. A decision that can be reversed in an afternoon is
not architecture, however important it feels; it should be made quickly by
whoever is closest to it. A decision that will constrain the system for a decade
is architecture, however small it looks — a column type, a tenancy assumption, a
public URL shape.

**Corollary:** the value of an architect is measured by how few decisions the
team is stuck with, not by how many they made.

---

## The Governing Criterion: Reversibility

**Decision.** Architectural decisions are classified by reversal cost, and the
rigour applied is proportional to it.

**Reasoning.** Our confidence in any given forecast is low — `21` names six
central assumptions that remain unvalidated, and over ten years the list of
things we were wrong about will be long. Given unreliable prediction, spending
effort on being right is spending it on the wrong variable. Spending it on
bounding the cost of being wrong is robust to bad forecasts, which is the
situation we are actually in (P7).

| Class | Reversal cost | Rigour required | Examples |
| --- | --- | --- | --- |
| **One-way door** | Rewrite or migration with data risk | ADR, spike, two reviewers, deliberate delay | Tenancy model, primary datastore, public API shape, domain model core |
| **Heavy door** | Bounded project, weeks | ADR, review | Module boundary, framework, service extraction |
| **Two-way door** | Refactor, days | Normal review | Internal patterns, library choice inside a module, caching approach |
| **Revolving door** | Hours | Just do it | Naming inside a module, private helpers, local structure |

**Alternatives considered.** *Uniform rigour* — every decision gets an ADR:
produces process theatre, slows revolving-door decisions to a crawl, and trains
the team to route around the process. *Seniority-based* — architects decide,
others implement: creates a bottleneck, disconnects decisions from the context
they affect, and does not scale past one team. *Consensus on everything*:
slowest possible, and produces decisions nobody owns.

**Trade-offs.** Classification is itself a judgment, and it will sometimes be
wrong — a decision assumed reversible turns out not to be. This is mitigated by
the reversibility ledger in `28`, which records the assumption so it can be
corrected. The failure is recoverable; uniform rigour's failure (a team that
routes around process) is not.

**Benefits.** Fast decisions where speed is free. Careful decisions where care is
essential. Effort concentrated where it changes outcomes.

**Long-term impact.** This is the mechanism that keeps a ten-year-old system
changeable. Systems ossify not because their decisions were wrong but because
too many of them became load-bearing without anyone noticing.

### The last responsible moment

Decisions are deferred until deferring further would cost more than deciding
now — not deferred indefinitely, and not made at the earliest possible moment
"to get it out of the way."

Early decisions are made with the least information the team will ever have. The
discipline is to identify what must be true *regardless* of the decision, build
that, and let the decision arrive with more evidence behind it.

**This is why `08` defers sharding and `06` defers a dedicated vector store.**
Both are heavy doors today; both become cheaper to decide correctly once real
data exists. What could not be deferred — tenant-partitionable access,
statelessness, retrieval behind an interface — is exactly the work that keeps
those doors open.

**Boundary:** deferral requires knowing the trigger that will force the decision
and monitoring for it. A deferred decision without a trigger is not deferred; it
is forgotten, and it will be made under pressure by whoever hits the wall first.

---

## Architectural Characteristics: Choosing What to Sacrifice

**Decision.** We explicitly rank the qualities the architecture optimizes for,
and explicitly name what we sacrifice.

**Reasoning.** Every architectural quality trades against others. Availability
trades against consistency; performance against simplicity; flexibility against
comprehensibility. An architecture claiming to maximize everything has simply
not made its trade-offs explicit — which means they were made implicitly,
inconsistently, and by accident.

**Our ranking, in order:**

| Rank | Characteristic | Why here |
| --- | --- | --- |
| 1 | **Tenant isolation / security** | Only unrecoverable failure class (`19` CR-1) |
| 2 | **Data correctness and traceability** | The product *is* the record (P2) |
| 3 | **Maintainability / changeability** | The ten-year mission; dominates total cost |
| 4 | **Availability** | Customers depend on it, but degradation beats corruption |
| 5 | **Scalability** | Required at Phase 4 targets, not before (P10) |
| 6 | **Performance** | Must meet stated budgets; beyond that, not a differentiator |
| 7 | **Cost efficiency** | Constrains, but is not optimized at the expense of the above |

**What we explicitly sacrifice:**

- **Raw throughput per node.** PHP is not the fastest runtime. Accepted — our
  bottleneck is IO and inference, not CPU (`06`).
- **Deployment independence per module.** The monolith deploys as a unit.
  Accepted — the option to extract is preserved (D-39).
- **Best-of-breed per concern.** Postgres does search, vectors and graph
  traversal adequately rather than any of them optimally. Accepted — one system
  to operate, secure and reason about (P12).
- **Instant AI responses.** All inference is asynchronous (P-9). Accepted — this
  buys availability independence and bounded latency.
- **Maximum flexibility.** Opinionated structure constrains what engineers can
  do. Accepted deliberately — constraint is what makes a large codebase
  navigable.

**Alternatives considered.** *Rank nothing* — the common approach; trade-offs
still get made, but implicitly, inconsistently, and differently by each engineer
under each deadline. *Optimize everything to a high standard* — the ranking
question reappears the first time two goals conflict, which is immediately, and
is then resolved by whoever is loudest. *Rank per module* — genuinely appealing
for a modular system, and rejected because cross-cutting decisions (tenancy,
deploy model, datastore) would have no resolution rule at all.

**Trade-offs.** A published ranking can be cited to shut down legitimate
concerns ("performance is rank 6, so this doesn't matter"). The ranking governs
*conflicts*, not whether a characteristic matters — every one of them has stated
minimum thresholds in `04`, and falling below a threshold is a defect regardless
of rank.

**Benefits.** Conflicts resolve consistently and quickly, without re-arguing
first principles each time. Reviewers have an objective basis for saying "this
optimizes rank 6 at the expense of rank 3." And naming the sacrifices means we
stop apologizing for them — asynchronous AI is a deliberate design position, not
a limitation to be excused.

**Long-term impact.** Explicit sacrifice is what prevents architectural drift.
When each decision independently optimizes whatever seemed important that week,
the result after ten years is a system with no coherent character and no
predictable behaviour under stress.

---

## Coupling and Cohesion

**Decision.** Coupling is the primary structural metric; boundaries are placed to
minimize it, and coupling is characterized by kind, not merely counted.

**Reasoning.** Every maintainability problem in a large system reduces to
coupling: a change here forces a change there. Cohesion is the mirror — things
that change together should live together. Boundary placement is the exercise of
maximizing cohesion within and minimizing coupling across.

But "coupling" as a single count is too blunt to guide decisions. Some
dependencies are benign; others are corrosive. The useful distinction is by
**kind and strength**:

| Kind | Strength | Example | Stance |
| --- | --- | --- | --- |
| By name | Weakest | Calling a published method | Fine |
| By type | Weak | Depending on an interface | Preferred at boundaries |
| By meaning | Moderate | Both sides agree what status `3` means | Replace with shared enums in the contract |
| By position | Strong | Argument order, tuple shape | Avoid — use named structures |
| By algorithm | Strong | Two components must implement the same rule | **Extract — this is duplicated knowledge (P11)** |
| By timing | Strongest | A must run before B, implicitly | **Eliminate — make ordering explicit or event-driven** |
| By value / identity | Strongest | Shared mutable state | **Never across boundaries** |

**The rule:** the further apart two things are, the weaker the coupling between
them must be. Inside a class, coupling by position is fine. Across a module
boundary, only name and type coupling are acceptable — which is exactly what
D-34's preference for events over direct calls achieves.

**Alternatives considered.** *Layered architecture as the primary decomposition*
— organizes by technical role, which guarantees that every business change cuts
across every layer. Rejected (D-106). *Component count or size limits* — easy to
measure, unrelated to changeability; a small module with timing coupling is
worse than a large cohesive one.

**Trade-offs.** Weak coupling costs indirection, and indirection costs
readability (P4). Events in particular make control flow harder to follow — which
is why distributed tracing across event boundaries is a Phase 1 requirement
(O-1), not a later nicety.

**Benefits.** Modules become independently testable and independently
changeable, which is what makes the boundaries in `03` worth having rather than
decorative. Characterizing coupling by kind also makes review concrete: "this
introduces timing coupling across a module boundary" is a specific, arguable
observation, where "this feels coupled" is not.

**Long-term impact.** Coupling is what determines whether year-eight changes take
days or quarters. It is also the property most easily destroyed by
well-intentioned convenience, which is why it is enforced mechanically (M-2)
rather than reviewed.

### Where a boundary goes

Four heuristics, in priority order:

1. **The language changes.** When the same word means different things on either
   side — a "requirement" in Requirements is a specification; in Estimation it is
   a cost driver — that is a context boundary. This is the strongest signal and
   the one we trust most.
2. **The rate of change differs.** Things that change together belong together;
   things that change on independent schedules should not be coupled. Prompts
   change weekly, domain rules change yearly — hence D-30.
3. **The consumer differs.** Commercial data serves external stakeholders; project
   data serves the delivery team. Different audiences imply different
   confidentiality and different evolution.
4. **The failure profile differs.** A component that fails for entirely different
   reasons, at a different rate, wants isolation (A-5).

**Anti-heuristics — reasons that look like boundaries but are not:** team
convenience, file count, technical similarity ("all the AI stuff"), or a desire
to use a different technology. The last is the most seductive and the most
frequently wrong.

---

## Complexity Governance

**Decision.** Accidental complexity is treated as a defect; essential complexity
is contained rather than eliminated.

**Reasoning.** Some complexity is inherent — estimation with calibration,
traceability with versioning, multi-tenant isolation. That complexity cannot be
removed, only relocated. The engineering task is to concentrate it where it
belongs (the domain layer, the isolation mechanism) so that the rest of the
system stays simple, rather than smearing it thinly across everything.

Accidental complexity — complexity we added — is different: it can and should be
removed. Sources: premature abstraction (P11), configuration that could be a
constant, indirection with one implementation, and flexibility nobody requested.

**The complexity budget.** Every new concept — a pattern, a service, a store, an
abstraction — is charged against a finite budget of what a team can hold in
mind. This is why `05` runs one datastore rather than four, one architectural
style rather than a mix, and one internal layering pattern rather than several.
Uniformity is worth real capability sacrifice, because an engineer who
understands one module can then navigate all of them.

**Alternatives.** *Best tool per problem* — maximizes local fit, and the
aggregate cognitive and operational load is what actually breaks small teams.
*Single tool for everything* — we do this where possible, and accept its
limitations knowingly (Postgres for search and vectors). *No governance* —
complexity is then added by whoever is enthusiastic, and removed by nobody.

**Trade-offs.** We accept measurably worse tools for some concerns, and we will
occasionally be slower because the uniform pattern fits a particular problem
poorly. Engineers who enjoy per-problem optimality find this frustrating, which
is a real cost to morale and recruiting.

**Benefits.** An engineer who learns one module can navigate all of them.
Operational surface stays small enough that one person can hold it during an
incident. Onboarding (M-5) stays achievable as the system grows.

**Long-term impact.** Complexity is monotonic without active governance. Ten
years of "just one more pattern" produces a system where no individual
understands the whole, which is the condition under which safe change becomes
impossible.

---

## Architecture as Executable Constraint

**Decision.** Architectural rules are expressed as automated fitness functions
that fail the build, not as diagrams and documents.

**Reasoning.** Documented architecture describes intent; enforced architecture
describes reality. Over ten years the gap between them grows monotonically
unless something closes it continuously. Every architectural rule that matters
must therefore be executable: dependency direction, layer isolation, cross-module
access, endpoint authorization, tenant scoping, no-inference-in-request-path
(`13`).

**Alternatives.** *Review as enforcement* — catches most violations while
attention holds, misses the rest, and degrades under pressure exactly when
architecture is most at risk. *Periodic architecture review* — finds drift after
it is expensive. *Diagrams as the source of truth* — diverge from reality
immediately and are then actively misleading.

**Trade-offs.** Fitness functions cost effort to write and can be brittle; a
badly written one blocks legitimate work and gets disabled, which is worse than
never having it. They also cannot express everything — "is this the right
abstraction?" remains human judgment (D-162).

**Benefits.** The architecture in the repository *is* the architecture, at all
times, with no drift. New contributors — human or AI — cannot violate it
accidentally.

**Long-term impact.** This is the single most important mechanism for ten-year
architectural integrity. Documents decay; tests do not.

---

## How Architecture Changes

Architecture is not a phase. It is a continuous activity, and the discipline is
in how change is admitted.

| Mechanism | When | Cost |
| --- | --- | --- |
| **Refactor** | Structure is wrong, behaviour is right | Hours to days |
| **Restructure** | A boundary is misplaced | Days to weeks |
| **Extract** | A component's profile genuinely diverged (D-39) | Weeks |
| **Consolidate** | A split proved unnecessary | Weeks |
| **Strangle** | A subsystem must be replaced without stopping | Months |
| **Rewrite** | Almost never | Quarters, high risk |

**On rewrites.** A rewrite is justified only when the existing system cannot meet
a requirement it must meet, *and* incremental change cannot get there. Both
conditions, demonstrated, not felt. The usual rewrite motivation — "the code is
bad" — is a refactoring problem, and rewrites reliably reproduce the original's
problems while discarding a decade of embedded bug fixes nobody remembers making.

The strangler pattern is the default for large replacement: build the new
alongside the old, route incrementally, delete the old when traffic reaches zero.
Slower, and always the right choice at this scale of risk.

**Consolidation deserves equal standing with extraction.** Most architecture
guidance plans only for splitting, which is why over-split systems are common and
rarely fixed. If a boundary proves artificial — `03` flags Estimation and
Planning as candidates — merging it is a legitimate architectural improvement,
not an admission of failure.

---

## Conway's Law as a Design Tool

**Decision.** Team structure is chosen to match the desired architecture, not the
reverse.

**Reasoning.** Systems reflect the communication structure of the organizations
that build them. This is usually cited as a warning; it is more useful as a
lever. If we want vertical, independently deliverable slices, we need
cross-functional teams. If we split frontend and backend teams, we will get a
frontend/backend architectural seam whether or not it belongs there — which is
precisely why D-123 forbids it.

**Alternatives.** *Organize by technical specialization* — the conventional
structure; produces efficient specialists and layer-aligned handoffs, and the
architecture follows. *Organize by project* — flexible, and produces no durable
ownership, so quality decays in whatever nobody currently owns. *Ignore the
relationship* — the architecture still ends up matching the org chart; we simply
do not get to choose which one.

**Trade-offs.** Cross-functional teams need broader skills per engineer, which
raises hiring difficulty and reduces the depth of specialization available.
Context-aligned teams can also duplicate effort across contexts where a
specialist team would have shared it.

**Benefits.** Team boundaries reinforce module boundaries instead of cutting
across them, so ownership and architecture stay aligned as both change.

**Long-term impact.** Over ten years, organizational shape will change several
times. Each change is an architectural event. The rule — split by bounded
context, never by technology layer — must survive those changes, or the
architecture will be reorganized by accident.

---

## What Actually Survives Ten Years

The most important observation in this document, and the one that should shape
where care is invested:

| Artifact | Expected lifetime | Implication |
| --- | --- | --- |
| **The domain model and its vocabulary** | 10+ years | Deserves the most thought; hardest to change; changing it changes everything |
| **The database schema** | 10+ years | Data outlives every application that touches it |
| **Public API contracts** | 5–10 years | Consumers we do not control; deprecation is slow and expensive (`27`) |
| **Module boundaries** | 5–10 years | Expensive but possible to move |
| **Event schemas** | 5–10 years | Persisted and replayed; effectively permanent once consumed |
| **Backend framework** | 3–7 years | Replaceable if the domain is insulated (D-130) |
| **Frontend framework** | 3–5 years | Historically the fastest-churning layer |
| **Infrastructure** | 2–5 years | Portable if we depend on contracts, not vendor primitives |
| **The AI layer** | 1–3 years | Designed to be discarded (D-42) |

**The strategic conclusion: invest care in inverse proportion to expected
churn.** Time spent on the domain model and schema compounds for a decade. Time
spent perfecting the current AI orchestration will be discarded within two
years — which is why that layer is deliberately the most replaceable component,
and why insulating the domain from the framework (P5, D-130) is not
architectural purity but a direct bet on this table.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-217 | Architecture is defined as the decisions expensive to change; the goal is to minimize their number | Makes architecture actionable rather than aspirational |
| D-218 | Decisions classified by reversal cost; rigour proportional to it | Uniform rigour produces theatre and gets routed around |
| D-219 | Decisions deferred to the last responsible moment, with a monitored trigger | Later decisions are better informed; a deferral without a trigger is an ambush |
| D-220 | Architectural characteristics are explicitly ranked and sacrifices named | Unstated trade-offs get made implicitly and inconsistently |
| D-221 | Coupling characterized by kind and strength, not counted | Timing and algorithmic coupling are corrosive; name coupling is benign |
| D-222 | Only name and type coupling permitted across module boundaries | The further apart, the weaker the coupling must be |
| D-223 | Boundaries placed where the language changes, first heuristic | Strongest and most durable signal of a genuine context |
| D-224 | Uniformity preferred over per-problem optimality | Cognitive budget is finite and shared |
| D-225 | Architectural rules expressed as build-failing fitness functions | Documents decay; tests do not |
| D-226 | Consolidation has equal standing with extraction | Over-split systems are common because only splitting is ever planned |
| D-227 | Rewrites require a demonstrated unmeetable requirement, not dissatisfaction | Rewrites reproduce old problems and discard embedded fixes |
| D-228 | Strangler pattern is the default for large replacements | Bounded risk; the system keeps working throughout |
| D-229 | Care invested in inverse proportion to expected churn | Domain and schema compound for a decade; the AI layer will be discarded |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Reversal-cost classification is wrong; a "two-way door" proves one-way | Committed to something expensive without the rigour it deserved | Reversibility ledger (`28`) records the assumption so it can be corrected on evidence |
| Fitness functions become brittle and are disabled | Architecture drifts silently with a green build | Failures reviewed for false positives and tuned, never removed wholesale (D-128) |
| Characteristic ranking used to dismiss legitimate concerns | Real defects argued away by citation | Every characteristic has a minimum threshold in `04`; below threshold is a defect regardless of rank |
| Deferred decisions forgotten rather than deferred | Decision made under pressure by whoever hits the wall | Trigger required and monitored at the time of deferral |
| Architecture treated as a completed artifact | Drift as the system outgrows its original shape | Reviewed at phase boundaries; ADRs for every significant change |
| Philosophy read and not applied | Elaborate documentation, unchanged behaviour | The parts that can be executed are executed; the rest is checked in review |

## Dependencies

- **Depends on:** engineering principles (`22`), current architecture (`05`).
- **Depended on by:** technology selection (`24`), future evolution (`28`),
  review process (`16`), ADR process.

## Future Improvements

- Publish the fitness function catalogue alongside the first module, so the
  executable architecture is visible as a list.
- Add a decision-classification field to the ADR template once the reversal-cost
  taxonomy has been used enough to validate it.
- Revisit the characteristic ranking after Phase 2, when real customer pressure
  will reveal whether it matches how we actually behave.
