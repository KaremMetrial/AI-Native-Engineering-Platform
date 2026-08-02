# Engineering Principles

## Purpose

Give each principle in the engineering charter its reasoning, its alternatives,
its cost, and — most importantly — **its boundaries**. The charter states what
we do. This document states why, what we gave up to do it, and when the
principle stops applying.

A principle without boundaries becomes dogma, and dogma is applied hardest
exactly where it fits worst. Ten-year maintainability depends on engineers who
can tell the difference, which requires them to know the reasoning, not just the
rule.

## Scope

**In scope:** the principles governing engineering judgment, their tiers,
their justification, their limits, and how conflicts between them are resolved.

**Out of scope:** mechanical standards (`13`), architectural method (`23`), and
the charter itself, which states the principles this document justifies.

---

## Why Principles Need Justification

Rules propagate badly. An engineer given "prefer composition over inheritance"
without the reason will apply it to a case where inheritance is genuinely
correct, produce something worse, and either learn to distrust the rule or
learn to apply it blindly. Both outcomes are bad, and the second is worse
because it is invisible.

A principle with its reasoning attached can be **evaluated** in a novel
situation. This matters more here than in a typical codebase for two reasons:

- **Ten-year horizon.** Nobody who writes the first line will be the last person
  to maintain it. The reasoning is what survives the people.
- **AI-assisted contribution.** An agent given a bare rule applies it literally
  and wrongly at the edges. An agent given the reason applies it correctly in
  situations the rule never anticipated. This is the same reason `18` requires
  every recommendation to carry its rationale.

---

## Principle Tiers

Not all principles carry equal weight, and pretending they do makes conflict
resolution impossible.

| Tier | Meaning | Override |
| --- | --- | --- |
| **Inviolable** | Breach is unrecoverable or compounds without bound | Never |
| **Strong default** | Correct in the large majority of cases | Requires stated justification in review |
| **Contextual** | Genuinely situational; judgment governs | No justification needed to deviate |

---

## Tier 1 — Inviolable

### P1 · Isolation is enforced by the system, never by discipline

**Decision.** Security and tenancy boundaries are enforced by mechanisms that
fail closed, never by engineers remembering to apply them.

**Reasoning.** Human discipline has a defect rate. Over ten years and hundreds
of thousands of changes, any control depending on universal, permanent
correctness will be breached — not because engineers are careless, but because
the failure only has to happen once. A mechanism that fails closed converts an
inevitable human error into a visible bug instead of a silent breach.

**Alternatives.** Code review as the control (catches most cases, not all, and
degrades under time pressure). Convention plus documentation (no enforcement at
all). Periodic audit (detects breaches after they occur, which for a data leak
is too late).

**Trade-offs.** Mechanisms cost engineering time to build and can produce
friction — a query builder that always applies tenant scope occasionally
obstructs a legitimate cross-tenant administrative operation, which then needs
an explicit, audited escape hatch. We accept that friction deliberately.

**Benefits.** The failure mode of a mistake becomes "returns nothing" rather
than "returns someone else's data." Engineers can move fast *because* the
dangerous paths are closed.

**Long-term impact.** This is the principle that most determines whether the
platform survives. It also compounds favourably: each mechanism built stays
built, protecting every future engineer including those who never read this
document.

**Boundary.** None. This has no exceptions.

### P2 · Correctness of the record over convenience of the workflow

**Decision.** Where a shortcut would make the Delivery Graph less accurate, the
shortcut loses.

**Reasoning.** The graph is the product (`01`). An artifact whose lineage is
wrong, a version that was mutated in place, an approval that was inferred rather
than recorded — each is a small convenience that destroys the asset the company
is built on. Traceability that is 95% accurate is not 95% as valuable; it is
close to worthless, because a consumer cannot know which 5% is wrong.

**Alternatives.** Best-effort lineage (cheaper, faster, and quietly useless).
Reconstructing lineage after the fact (impossible — the information does not
exist later).

**Trade-offs.** More writes, more storage, more care at every mutation point.
Some operations that could be a single update become an append plus a link.

**Benefits.** Every claim the product makes about traceability is defensible.
Impact analysis is exact rather than approximate.

**Long-term impact.** Compounding. A graph that has been accurate for eight
years is an asset no competitor can replicate; one that has been approximate for
eight years is a liability nobody trusts.

**Boundary.** None.

### P3 · Fail fast, fail loud, fail closed

**Decision.** Invalid state raises immediately. Ambiguous authority denies.
Missing context is fatal, never defaulted.

**Reasoning.** A system that continues past an invalid state converts a
localized, diagnosable failure into a distant, mysterious one. The distance
between cause and symptom is the single largest driver of debugging cost, and
debugging cost dominates maintenance cost over a decade.

The "fail closed" half is the security-relevant one: when a job cannot determine
its tenant, running it unscoped is catastrophic while failing it is merely
inconvenient (D-57).

**Alternatives.** Defensive defaulting (hides bugs until they surface as data
corruption). Logging and continuing (produces a log nobody reads and a system in
an undefined state). Fail open for availability (appropriate for some systems;
catastrophic for a multi-tenant one).

**Trade-offs.** More visible failures, especially early. Some of them will be
our own over-strictness. This is a real cost and it is worth paying.

**Benefits.** Failures point at their cause. Bad states cannot propagate.

**Long-term impact.** Determines whether a ten-year-old system is debuggable.
Systems built on defensive defaulting accumulate undiagnosable behaviour until
nobody will touch them.

**Boundary.** User-facing resilience is different from internal correctness: a
degraded third-party integration should degrade gracefully (A-5), not crash the
page. Fail fast applies to *invariant violation*, not to *expected external
failure*.

---

## Tier 2 — Strong Defaults

### P4 · Optimize for the reader

**Decision.** When writing clarity and reading clarity conflict, reading wins.

**Reasoning.** Code is read far more often than it is written, and over a decade
the ratio becomes extreme. The person reading is usually not the person who
wrote it, has less context, and is often under pressure — debugging an incident
or making a change they do not fully understand yet. Every minute saved writing
is repaid many times over in reading.

**Alternatives.** Optimize for writing speed (wins the sprint, loses the
decade). Optimize for cleverness or concision (optimizes for the author's
satisfaction, which is not an engineering goal).

**Trade-offs.** More verbose code. Sometimes more lines to express the same
thing. Occasionally slower to write.

**Benefits.** Lower onboarding cost (M-5), faster incident response, lower defect
rate on changes to unfamiliar code.

**Long-term impact.** The single largest determinant of whether the codebase is
still pleasant to work in at year ten.

**Boundary.** Does not justify unbounded verbosity. A wall of explanatory
ceremony is also hard to read. The test is whether a competent engineer
unfamiliar with the code can follow it — not whether every step is spelled out.

### P5 · Explicit over implicit

**Decision.** Dependencies, effects and control flow are visible at the point of
use.

**Reasoning.** Implicit behaviour — magic resolution, global state, convention
that only works if you know it — is invisible to a reader and therefore
invisible to a reviewer. It is also invisible to static analysis, which means it
cannot be enforced (P8).

**Alternatives.** Convention-heavy frameworks that reduce boilerplate through
implicit wiring. Genuinely faster to write, and the ecosystem default. Rejected
in the domain and application layers, accepted at the framework edge.

**Trade-offs.** More boilerplate. We reject some idiomatic framework
conveniences (facades, service location, ambient request state — see `13`) that
the wider community uses happily.

**Benefits.** Dependencies visible in constructors, so testability follows
automatically. Effects visible at call sites, so review catches what it should.

**Long-term impact.** Implicit coupling is the mechanism by which systems become
"impossible to change safely." Every implicit dependency is one a future
engineer will break without knowing it existed.

**Boundary.** Framework-edge code (routing, DI configuration, serialization) is
where implicit wiring earns its keep. The rule applies inward, hardest at the
domain.

### P6 · Simple over clever

**Decision.** Prefer the obvious solution unless a measured requirement demands
otherwise.

**Reasoning.** Cleverness has a maintenance cost paid by everyone who reads the
code afterwards, and the person best placed to understand a clever solution is
the person who wrote it — who will not be available in year six. Most cleverness
optimizes something that was not the bottleneck.

**Alternatives.** Optimize aggressively by default (see `26` — almost always
premature). Abstract aggressively for future flexibility (see P10 — usually
wrong about the future).

**Trade-offs.** Occasionally leaves measurable performance or elegance on the
table. When a profile shows it matters, we take it — with a comment explaining
why the simple version was insufficient.

**Benefits.** Lower defect rate, faster review, broader set of engineers able to
work on any given area.

**Long-term impact.** Simple systems can be changed by people who did not build
them. That property *is* ten-year maintainability.

**Boundary.** Simple is not the same as naive. An O(n²) algorithm on unbounded
input is not simple, it is wrong. Simplicity is judged against the actual
requirement.

### P7 · Reversibility over prediction

**Decision.** Where the future is uncertain, prefer the option that is cheaper
to undo over the option that seems most likely to be right.

**Reasoning.** Our forecasts about which boundaries, technologies and features
will matter are unreliable — `21` names six central assumptions that are
explicitly unvalidated. Given unreliable prediction, optimizing for correctness
of the guess is optimizing the wrong variable. Optimizing for the cost of being
wrong is robust to bad forecasts.

This is why `06` ranks reversal cost second in its evaluation criteria, and why
`23` makes it the primary architectural criterion.

**Alternatives.** Predict harder — more analysis, more design up front. Has real
value for genuinely irreversible decisions (schema, tenancy model) and sharply
diminishing returns elsewhere. Commit early for velocity — fine when reversal is
cheap, which is exactly the case this principle identifies.

**Trade-offs.** Sometimes costs more up front: an abstraction that preserves
optionality is more work than a direct call. Applied indiscriminately it becomes
speculative generality (P10), which is why it is a default and not inviolable.

**Benefits.** Bad decisions stay cheap. The AI layer — our fastest-moving,
least predictable component — is deliberately the most replaceable (D-42).

**Long-term impact.** The central mechanism of `28`. A ten-year system is not one
whose decisions were right; it is one whose wrong decisions could be undone.

**Boundary.** Reversibility is not free. Where the cost of preserving optionality
exceeds the expected cost of being wrong, commit. A one-way door with high
confidence and high value is worth walking through.

### P8 · Automate enforcement; exhortation does not scale

**Decision.** Any standard worth having is worth automating. Standards that
cannot be automated are advisory and are labelled as such.

**Reasoning.** Documented standards decay: they are read once at onboarding,
followed diligently for a month, and then eroded by deadline pressure, staff
turnover and honest disagreement. Automated standards do not decay; they hold
identically at year one and year ten, for every contributor including AI agents.

**Alternatives.** Review as the enforcement mechanism (works at small scale,
degrades as volume grows, and consumes the reviewer attention that should go to
judgment — D-126). Culture (real, but not transferable to new hires at the rate
we need, and invisible to agents).

**Trade-offs.** Tooling investment, tuning cost, and false positives that
irritate. Some genuinely important standards — naming quality, abstraction fit —
resist automation and must stay human.

**Benefits.** Standards hold under pressure, which is exactly when they matter.
Review capacity is preserved for what only humans can do.

**Long-term impact.** The mechanism by which quality survives growth. Every
manual standard is one that will be quietly abandoned in year three.

**Boundary.** Do not automate a standard you cannot articulate. A rule enforced
by a tool nobody can explain becomes cargo cult, and the first time it blocks
something legitimate it will be disabled wholesale.

### P9 · Make the change easy, then make the easy change

**Decision.** When a change is hard because of existing structure, restructure
first — in a separate change — then make the change.

**Reasoning.** Forcing a change into an ill-fitting structure produces the worst
of both: the change is harder than it should be, and the structure gets worse,
making the next change harder still. This compounds, and compounding is what
turns a healthy codebase into a feared one.

**Alternatives.** Force it through and clean up later (later does not arrive).
Refactor and change together (produces an unreviewable diff where the reviewer
cannot separate intentional from incidental — explicitly rejected in D-122).

**Trade-offs.** Two changes instead of one; slower for the immediate task.
Occasionally the restructuring turns out to have been unnecessary.

**Benefits.** Each change leaves the code better positioned for the next.
Reviewable diffs.

**Long-term impact.** Determines the direction of the codebase's trajectory.
Over ten years, trajectory dominates starting position.

**Boundary.** Not a licence for unbounded refactoring. The restructuring should
be the minimum that makes the pending change straightforward — not the ideal
design of that area.

### P10 · Build for today's requirement; do not foreclose tomorrow's

**Decision.** Solve the problem in front of you, while avoiding decisions that
make the anticipated next problem unsolvable.

**Reasoning.** These are two distinct disciplines and conflating them causes both
failure modes at once. Speculative generality — building for imagined future
requirements — produces abstractions that fit no real case, cost maintenance
forever, and are usually wrong about the future anyway. But some decisions
genuinely do foreclose the future: non-tenant-partitionable queries, stateful
request handling, synchronous AI calls. Those cost nothing to avoid now and are
prohibitive to fix later.

`08` applies this explicitly: stateless from day one (cheap now, impossible to
retrofit); sharding later (expensive now, tractable later *because* of the
first).

**Alternatives.** Build for scale from the start (waste, and slower to the
learning that would tell us what to build). Ignore the future entirely (cheap
until the day it is not, then catastrophic).

**Trade-offs.** Requires judgment about which decisions are foreclosing, and
that judgment is sometimes wrong in both directions.

**Benefits.** Avoids the dominant failure mode of ambitious platforms — years of
foundation-building before anything is sellable — without painting into corners.

**Long-term impact.** The discipline that lets a system stay small and fast for
years and then scale when it must, instead of being slow and complex from the
start for a load that never arrives.

**Boundary.** The test for "foreclosing" is concrete: can this be changed later
by a bounded refactor, or does it require rewriting everything that depends on
it? Only the second justifies present cost.

### P11 · Duplication is cheaper than the wrong abstraction

**Decision.** Extract a shared abstraction when the duplication is proven to be
the *same knowledge*, not when two pieces of code merely look alike.

**Reasoning.** This refines the charter's prohibition on duplicated logic rather
than contradicting it, and the distinction is the important part. Duplicated
*knowledge* — one business rule expressed in two places — is a genuine defect:
the two copies will diverge and one will be wrong. Incidental *similarity* — two
pieces of code that resemble each other today for unrelated reasons — is not.

Abstracting incidental similarity couples two things that have no reason to
change together. When they inevitably diverge, the abstraction grows
conditionals and parameters until it is harder to understand than the
duplication it replaced — and it is now very hard to unwind, because both
callers depend on it.

**Alternatives.** Extract on the second occurrence (the common reflex; produces
premature abstraction). Never extract (produces genuine knowledge duplication
and divergent bugs).

**Trade-offs.** Tolerating some duplication temporarily, which looks like
sloppiness and will be flagged in review by engineers applying DRY
mechanically. The reasoning must be stated when it is.

**Benefits.** Abstractions that exist are ones that earned their place, so they
are stable rather than accreting special cases.

**Long-term impact.** Wrong abstractions are among the most expensive artifacts
in a long-lived codebase — they are load-bearing, widely depended on, and
resistant to removal. Not creating them is worth more than removing them later.

**Boundary.** This is not permission to copy-paste business rules. A rule that
must be consistent across contexts — a tenant scoping check, a pricing
calculation, an authorization decision — is duplicated knowledge and must be
extracted the *first* time it appears twice.

### P12 · Boring technology by default

**Decision.** Spend the innovation budget on the differentiator; use proven,
well-understood technology everywhere else.

**Reasoning.** Every novel technology carries an unknown-unknowns cost: failure
modes nobody has documented, ecosystem gaps, hiring difficulty, and operational
surprises that arrive during incidents. That cost is worth paying where the
technology *is* the product. It is pure waste elsewhere. Our innovation budget
is spent on the Delivery Graph and the AI layer; spending it again on
infrastructure would be spending a budget we do not have twice.

**Alternatives.** Best-of-breed everywhere (maximizes capability, maximizes
operational surface, and the aggregate learning cost is what actually kills
small teams). Ultra-conservative everywhere (would preclude pgvector and modern
model providers, which are genuinely required).

**Trade-offs.** We forgo genuine capability at the margin and are occasionally
late to real improvements.

**Benefits.** Predictable operations, answerable questions, hireable skills, and
attention concentrated where it differentiates.

**Long-term impact.** Boring technology ages well: it is still supported,
documented and staffable in year ten. Novel technology frequently is not, and
migrating off an abandoned dependency is unbudgeted work.

**Boundary.** "Boring" means proven, not old. A well-established technology that
solves the problem directly beats an older one requiring workarounds.

### P13 · Delete aggressively

**Decision.** Unused code, dead flags, stale documentation and unused
dependencies are removed, not archived.

**Reasoning.** Everything retained has a cost: it is read, searched, maintained,
scanned for vulnerabilities, and — worst — trusted. Dead code misleads readers
into thinking it matters. Stale documentation is actively harmful because a
reader cannot tell it is stale (D-189). Version control is the archive; keeping
a second copy in the working tree serves nobody.

**Alternatives.** Comment it out (worst option — invisible to tooling, visible to
readers). Keep behind a permanent flag (combinatorial state, untestable).

**Trade-offs.** Occasionally something is deleted that would have been useful,
and must be recovered from history. This is a minor, bounded cost.

**Benefits.** Smaller surface to understand, search, secure and maintain.

**Long-term impact.** Codebases grow monotonically unless deletion is a norm.
A ten-year-old system where nothing was ever deleted is one where most of the
code is unreachable and nobody knows which part.

**Boundary.** Deletion still follows the deprecation process for anything with
external consumers (`27`).

---

## Tier 3 — Contextual

These are genuinely situational. Deviating requires no justification; applying
them dogmatically is the more common error.

### P14 · Composition over inheritance

Inheritance couples subclass to superclass implementation permanently and across
the entire hierarchy. Composition keeps the relationship explicit and
changeable. **But** inheritance is correct for genuine is-a relationships with
stable, well-understood hierarchies — framework base classes, a closed set of
domain variants. The `final`-by-default rule (D-129) is what makes this
contextual rather than prohibitive: inheritance is opened deliberately, so it is
a decision rather than an accident.

### P15 · Immutability where practical

Immutable values eliminate an entire class of aliasing and concurrency bugs and
make reasoning local. Value objects and domain events are immutable without
exception. **But** entities have identity and lifecycle by definition, and
forcing immutability onto them produces awkward copying. Immutability is a tool,
not a religion.

### P16 · Domain-Driven Design and Clean Architecture where the complexity earns it

Both pay for themselves in the complex core — Requirements, Estimation, Design,
Planning, the Graph. **But** applying full layering to a feature-flag lookup is
ceremony that costs velocity and buys nothing (D-33). A module's structure
should match its complexity, and reviewers check that in both directions:
under-structured complexity and over-structured triviality are both defects.

### P17 · Convention over configuration

Conventions reduce decisions and make code predictable. **But** a convention that
is not written down is tribal knowledge, and one that is not enforced is a
suggestion (P8). Adopt a convention only if it is documented and, where
possible, checked.

---

## Resolving Principle Conflicts

Principles conflict routinely. Most guidance omits this, which makes the
guidance useless exactly when it is needed.

**Precedence order:**

1. **Tier 1 always wins.** Isolation, record correctness and fail-closed beat
   everything, including delivery deadlines.
2. **Reversibility (P7) breaks ties between strong defaults.** When two defaults
   disagree and neither is clearly stronger, prefer the option that is cheaper to
   undo.
3. **Reader (P4) beats writer convenience.** Always.
4. **Today's requirement (P10) beats speculative structure**, unless the
   structure is genuinely foreclosing.
5. **When still unresolved, escalate to an ADR.** A conflict that cannot be
   resolved by these rules is, by definition, a decision significant enough to
   record.

**Worked examples:**

| Conflict | Resolution |
| --- | --- |
| Simple (P6) vs explicit (P5) — a concise implicit helper vs verbose explicit wiring | Explicit wins in domain and application layers; simple wins at the framework edge |
| DRY vs wrong abstraction (P11) — two similar-looking handlers | Duplicate until the shared knowledge is proven; then extract |
| Reversibility (P7) vs simplicity (P6) — an abstraction that preserves optionality | Simple wins unless the decision is genuinely one-way; then reversibility wins |
| Fail fast (P3) vs graceful degradation (A-5) — AI provider is down | Both: fail the *job* fast and loudly; degrade the *product* gracefully. Different scopes. |
| Automate (P8) vs simple (P6) — enforcement tooling adds complexity | Automate anyway for Tier 1 concerns; weigh for others |
| Delete (P13) vs reversibility (P7) — removing an unused capability | Delete; version control is the reversal path |

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-210 | Principles are tiered: inviolable, strong default, contextual | Undifferentiated principles make conflict resolution impossible |
| D-211 | Every principle states its boundary — where it stops applying | A principle without limits is applied hardest where it fits worst |
| D-212 | Reversibility (P7) is the tie-breaker between competing defaults | Our forecasts are unreliable; optimizing the cost of being wrong is robust to that |
| D-213 | Duplicated *knowledge* is a defect; incidental similarity is not (P11) | Wrong abstractions are more expensive and harder to unwind than duplication |
| D-214 | Speculative generality and foreclosing decisions are distinguished explicitly (P10) | Conflating them produces both failure modes simultaneously |
| D-215 | Principle conflicts have a stated precedence order | Guidance that ignores conflict is useless exactly when it is needed |
| D-216 | Unresolvable principle conflicts escalate to an ADR | Such a conflict is by definition an architecturally significant decision |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Principles cited as authority to win arguments rather than to reason | Dogma; worse decisions defended by quotation | Boundaries are stated for every principle; citing one without its boundary is not an argument |
| Tier 2 justifications become a rubber stamp | Defaults erode silently | Deviations are recorded in review; recurring deviations signal the default is wrong and should be changed openly |
| P11 misread as permission to duplicate business rules | Divergent logic; correctness defects | Boundary explicitly states the first-occurrence rule for shared knowledge |
| Contextual tier read as "optional" and ignored entirely | Loss of genuinely useful defaults | Tier 3 is about judgment, not indifference; review still questions unjustified deviation |
| Principles never revisited as the system changes | Guidance ossifies around a system that no longer exists | Reviewed at phase boundaries with the rest of the foundation (D-208) |

## Dependencies

- **Depends on:** the engineering charter, which states the principles this
  document justifies.
- **Depended on by:** architecture philosophy (`23`), technology selection
  (`24`), coding standards (`13`), review process (`16`).

## Future Improvements

- Add worked examples from the real codebase once Phase 1 produces them;
  abstract examples teach less than concrete ones.
- Track which principles are most often deviated from — a frequently overridden
  default is either wrong or badly explained, and both are fixable.
