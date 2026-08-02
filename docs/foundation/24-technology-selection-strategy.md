# Technology Selection Strategy

## Purpose

Define how technology enters the platform, how it is maintained, and — the part
most organizations never define — **how it leaves**. `06` records the choices we
have made. This document governs the hundreds of choices that will be made over
the next decade by people who have not yet joined.

## Scope

**In scope:** the innovation budget, selection criteria, the technology radar,
adoption process, dependency policy, build-versus-buy, upgrade cadence,
deprecation and sunset, and lock-in policy.

**Out of scope:** current technology decisions (`06`) and architectural method
(`23`).

---

## The Innovation Budget

**Decision.** The platform spends its innovation budget on the Delivery Graph
and the AI layer. Everywhere else, technology is chosen to be unremarkable.

**Reasoning.** Every unfamiliar technology carries a cost that is invisible at
selection time and substantial afterwards: undocumented failure modes, ecosystem
gaps discovered mid-project, operational surprises that arrive during incidents,
and a hiring pool that shrinks as specificity rises. A team can absorb a small
number of these simultaneously. Past that number, the aggregate learning and
operational load consumes the capacity that was supposed to go into the product.

The budget framing makes the trade-off explicit: novelty is not free, the
allowance is finite, and spending it on infrastructure means not spending it on
the thing that differentiates us.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| Best-of-breed for every concern | Maximizes local capability. Rejected: the aggregate operational surface and learning cost is what actually breaks small teams, and each addition is individually defensible — which is how it happens. |
| Maximum conservatism everywhere | Would preclude pgvector and current-generation model providers, both genuinely required. Rejected: "boring" means proven, not old. |
| Innovation budget spent on infrastructure | The failure mode where a team builds an impressive platform and a mediocre product. Rejected explicitly. |

**Trade-offs.** We knowingly forgo real capability at the margin, and we will
occasionally be late to genuine improvements. Some engineers find this
unmotivating, which is a real recruiting cost.

**Benefits.** Predictable operations. Questions have answers on the public
internet. Skills are hireable. Attention concentrates where it differentiates.

**Long-term impact.** Boring technology ages well — it is still supported,
documented and staffable in year ten. Novel technology frequently is not, and
migrating off an abandoned dependency is unbudgeted, unglamorous work that
arrives at the worst time.

---

## Selection Criteria

Applied in order. Earlier criteria dominate later ones — a technology failing
criterion 1 is not rescued by excelling at criterion 6.

| # | Criterion | Question |
| --- | --- | --- |
| 1 | **Requirement fit** | Does it satisfy the NFR that motivated it (`04`)? |
| 2 | **Reversal cost** | If this is wrong in three years, what does it cost to undo (`23`)? |
| 3 | **Operational burden** | Who runs it at 3am, and what do they need to know? |
| 4 | **Ecosystem and longevity** | Maintained? Funded? Will it exist in five years? |
| 5 | **Team fit** | Can we staff it, and how long to competence? |
| 6 | **Total cost** | Licence, infrastructure, and the engineering time it consumes |

**Reversal cost ranks second deliberately.** A technology that is excellent but
irreversible deserves more scrutiny than one that is merely good and easily
swapped. This is the criterion most often omitted, and its omission is why
organizations end up permanently married to a decision made in an afternoon.

### Longevity signals

Criterion 4 is the hardest to assess and the most consequential over a decade.
Signals we weigh:

**Positive:** multiple independent maintainers or a foundation; a release cadence
that is regular rather than sporadic; a published security process; commercial
entities depending on it in production; documentation written for operators, not
just for demos.

**Negative:** single-maintainer projects without succession; venture-funded
open-source with no revenue model; rapid major-version churn with breaking
changes; a community whose questions go unanswered; "the new X" positioning with
no production track record.

**A specific caution: popularity is not longevity.** A technology can be
extremely popular and abandoned three years later. Adoption by organizations
whose survival depends on it is a far better signal than star counts.

---

## The Technology Radar

**Decision.** Technologies are placed in one of four rings, and the ring
determines what may be built on them.

| Ring | Meaning | Permitted use |
| --- | --- | --- |
| **Adopt** | Proven here; the default choice for its concern | Anywhere |
| **Trial** | Being evaluated in a bounded, low-risk context | One bounded context, behind an interface, with an exit plan |
| **Assess** | Interesting; not yet evaluated | Spikes and prototypes only. Never in production. |
| **Hold** | Do not start new work with this | Existing usage only; new usage requires an ADR to justify |

**Reasoning.** Without a radar, technology enters informally — one engineer uses
something in one place, it spreads by copy-paste, and within a year it is load-
bearing without ever having been evaluated. The radar makes adoption a decision
with a name attached rather than an accretion.

**The Hold ring is the most valuable and the most neglected.** It is how a
technology is retired: marked Hold, new usage stops, existing usage migrates
opportunistically, and eventually the last consumer is removed. Without it,
nothing ever leaves, and the stack only accumulates.

**Alternatives.** *Approved list only* — binary and brittle; provides no path for
evaluating anything new, so evaluation happens covertly. *No governance* — the
accretion described above. *Architecture board approval per use* — a bottleneck
that gets routed around.

**Trade-offs.** Maintaining the radar is ongoing work, and it can become
bureaucratic if the rings are enforced pedantically for trivial libraries. It
governs *significant* technology: datastores, frameworks, infrastructure
components, and any dependency that would be hard to remove.

**Benefits.** Adoption becomes a decision with a name attached rather than an
accretion nobody chose. Engineers get a clear answer to "may I use this?"
without a meeting. And the stack becomes describable — a new joiner can read
what we use and, more usefully, what we are deliberately moving away from.

**Long-term impact.** The mechanism that keeps the stack from growing
monotonically. A ten-year-old system without one runs four caching libraries,
three HTTP clients and two ORMs, each introduced for a good reason nobody
remembers.

---

## Adoption Process

Proportional to reversal cost (`23`).

**Revolving/two-way door** — a library used inside one module, easily removed:
normal review, note the dependency, done.

**Heavy door** — a framework, a shared library, a new infrastructure component:

1. **Spike.** Timeboxed, against a real requirement, not a tutorial. The spike's
   purpose is to find the *problems*, not to confirm enthusiasm.
2. **ADR.** Alternatives, trade-offs, reversal cost, exit path.
3. **Trial ring.** One bounded context, behind an interface, with a defined
   evaluation period and success criteria set in advance.
4. **Promote or retire.** At the end of the period, Adopt or remove. **Not
   "leave it and see"** — that is how Trial becomes permanent without a
   decision.

**One-way door** — a datastore, a language, a tenancy mechanism: as above, plus a
proof of concept against realistic scale and an explicit statement of what
migrating away would cost.

**The evaluation period must have an end date set before it starts.** Otherwise
the trial ends when someone builds something important on it, which is the same
as never evaluating it.

---

## Dependency Policy

Dependencies are the largest attack surface (`09` supply chain) and the largest
source of unplanned upgrade work. Each one is a permanent trust decision.

**Before adding a dependency:**

| Question | Reject if |
| --- | --- |
| Could this be a small amount of our own code? | Yes, and it is under ~200 lines of well-understood logic |
| Is it maintained? | No release or security response in 12+ months |
| How large is its transitive tree? | Dozens of transitive dependencies for a small capability |
| Is the licence compatible? | Copyleft incompatible with our distribution model |
| What happens if it is abandoned? | No answer |
| Does it duplicate something we already have? | Yes — use the existing one or replace it, do not run both |

**The left-pad rule:** a dependency for something trivial is a permanent
liability for a temporary convenience. Small utilities are written, reviewed and
owned.

**The counter-rule, equally important:** do not write your own cryptography,
authentication, date/time handling, or parsing of complex formats. The failure
modes are subtle, security-relevant, and have consumed far more expert effort
than we can spare. The distinction is whether the problem is *simple and
well-understood* (write it) or *deceptively simple* (use the library).

**Ongoing:** SCA scanning blocking on critical/high (SEC-7), automated updates
with a test gate, 72-hour critical patch SLA (SEC-8), lockfiles committed, and
periodic review of unused dependencies for deletion (P13).

---

## Build, Buy, or Adopt

**Decision framework**, applied to any significant capability:

| Ask | If yes |
| --- | --- |
| Is this our differentiator? | **Build.** Never outsource the moat. |
| Is it a solved commodity with mature options? | **Buy or adopt.** Payments, email, e-signature, observability backends. |
| Is it undifferentiated but deeply coupled to our domain? | **Build**, small and owned. Integration cost would exceed the build. |
| Do we already own something that fits? | **Adopt it** — after verifying the fit rather than assuming it (D-20). |
| Would buying create unacceptable lock-in on a core capability? | **Build**, or adopt behind an abstraction. |

**Worked example — identity.** Undifferentiated (nobody buys us for our login
page), deeply security-critical, and we already own `metrial-auth`. The
framework says adopt — *after verification*, which is why TR-3 is the first item
in Phase 0 rather than an assumption.

**Worked example — AI orchestration.** Adjacent to the differentiator and
control over retrieval scoping is a hard requirement (T-7). A managed agent
platform would be faster to start and would surrender control of the component
closest to the moat. The framework says build, behind a provider abstraction —
which is what `06` decided.

**The most common error** is buying something that touches the differentiator
because it is faster in month one. The vendor's roadmap then constrains the
product's roadmap permanently.

---

## Version Upgrade Cadence

**Decision.** Stay current continuously. Minor and patch versions are upgraded
routinely and automatically; major versions are planned work within one release
cycle of availability, not deferred.

**Reasoning.** Upgrade cost is superlinear in how far behind you are. A team one
major version behind performs a routine upgrade; a team four versions behind
performs a migration project with no direct business value, which is therefore
never prioritized, which is why they are four versions behind. The trap is
self-reinforcing.

Staying current also keeps the security posture defensible: security patches
often land only on recent majors, so falling behind eventually means running
unpatched.

**Alternatives.** *Upgrade only when forced* — minimizes short-term effort and
maximizes total effort; the standard path to a legacy system. *Bleeding edge* —
absorbs other people's bugs; rejected (P12).

**Trade-offs.** Continuous upgrade consumes a steady share of the 20% allocation
(D-124), and occasionally an upgrade breaks something. It also means absorbing
churn we did not ask for, on someone else's schedule.

**Benefits.** Each upgrade is small enough to diagnose when it breaks. Security
patches are available on the versions we run. And the team retains the *habit* of
upgrading, which is most of the difficulty — teams that upgrade rarely find it
hard because they do it rarely.

**Long-term impact.** This single practice separates ten-year-old systems that
are pleasant to work in from ones nobody will touch. It is unglamorous and it is
the highest-leverage maintenance discipline available.

---

## Deprecation and Sunset

**Decision.** Removing technology is a defined process with an owner, not an
aspiration.

**Reasoning.** Organizations have processes for adopting technology and almost
never for removing it, which is why stacks only grow. Every technology retained
past its usefulness costs security scanning, upgrade effort, cognitive load, and
a constraint on everything around it.

**Process:**

1. **Mark Hold.** New usage stops immediately. This alone prevents the problem
   getting worse and costs nothing.
2. **Inventory.** Every usage located and recorded. Unknown usage is the reason
   removals stall.
3. **Owner and date.** An unowned removal does not happen.
4. **Migrate opportunistically**, then deliberately for the remainder. Code being
   touched for other reasons migrates as it is touched.
5. **Verify zero usage** by telemetry, not by search. Search misses dynamic
   usage.
6. **Remove and delete** — the dependency, the configuration, the documentation,
   the runbooks (P13).

**Sunset triggers** — any of these starts the process: unmaintained upstream; a
security issue with no fix; superseded by something already in Adopt; the
requirement that motivated it no longer exists; or operational cost exceeding its
value.

**Alternatives.** *Remove opportunistically without a process* — the usual
approach; removals start, stall at 90%, and leave both technologies in place
permanently, which is strictly worse than either alone. *Never remove* — honest
about what actually happens, and accumulates cost forever. *Big-bang removal
projects* — same failure mode as modernization projects: unbudgeted, no
customer-visible value, perpetually deprioritized.

**Trade-offs.** The process is slower than simply deleting things, and step 3
(owner and date) requires someone to accept work that produces no new
capability. This is genuinely hard to prioritize and must be defended explicitly.

**Benefits.** Removals actually complete. The stack shrinks as well as grows,
which is the only way it stays comprehensible.

**Long-term impact.** Determines whether year-ten engineers work with a curated
stack or an archaeological record of every decision ever made.

---

## Lock-In Policy

Lock-in is not binary and is not always bad. It is a cost that must be
proportionate to the value received.

| Acceptable | Unacceptable |
| --- | --- |
| Postgres-specific features (RLS) — deciding advantage, migration implausible | Cloud-provider primitives in domain logic |
| Managed services behind a standard interface (S3 API, OTel) | A vendor whose roadmap would constrain our product roadmap |
| Framework idioms at the edge | Framework coupling in the domain (D-130) |
| A single model provider as the *default* | A single model provider with no abstraction (D-51) |

**The test:** if this vendor doubled prices or degraded materially, what would it
cost to leave, and is that cost proportionate to what they provide? Where the
answer is uncomfortable, insert an abstraction — thin, capability-based, with
escape hatches, so it does not become lowest-common-denominator.

**Where we accept deep lock-in, we accept it explicitly** rather than
accidentally. Postgres RLS is the clearest case: it is a genuine one-way door,
taken deliberately because it is the strongest available defence for the failure
that would end the company.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-230 | Innovation budget spent on the Delivery Graph and AI layer only | Novelty has a finite allowance; spending it twice is spending what we do not have |
| D-231 | Selection criteria applied in order, reversal cost ranked second | The most-omitted criterion, and the reason organizations end up permanently married to afternoon decisions |
| D-232 | Longevity assessed by dependency of serious users, not popularity | Popular technologies get abandoned; depended-upon ones rarely do |
| D-233 | Four-ring technology radar governing significant technology | Without it, adoption happens by accretion and nothing is ever evaluated |
| D-234 | The Hold ring is the retirement mechanism | Nothing leaves a stack that has no exit ring |
| D-235 | Trial adoption has an end date set before it starts | Otherwise the trial ends when something important is built on it |
| D-236 | Dependencies judged on transitive weight, maintenance and abandonment risk | Each is a permanent trust decision and part of the supply-chain surface |
| D-237 | Write simple utilities; never write cryptography, auth or date/time handling | The distinction is simple-and-understood versus deceptively simple |
| D-238 | Never buy or outsource the differentiator | The vendor's roadmap becomes the product's roadmap |
| D-239 | Stay continuously current on versions; majors planned within one cycle | Upgrade cost is superlinear in distance; the trap is self-reinforcing |
| D-240 | Deprecation is a defined six-step process with an owner and a date | Unowned removals do not happen |
| D-241 | Zero usage verified by telemetry, not by code search | Search misses dynamic usage |
| D-242 | Lock-in accepted explicitly and proportionately, never accidentally | Postgres RLS is worth it; cloud primitives in domain logic are not |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Radar becomes bureaucratic and is bypassed | Informal adoption returns, with less visibility than before | Governs significant technology only; trivial libraries follow normal review |
| Boring-technology bias causes us to miss a genuine step change | Competitive disadvantage | Assess ring exists precisely for this; spikes are encouraged, production use is not |
| Upgrade cadence deprioritized under delivery pressure | The superlinear trap; a legacy system by year five | Part of the standing 20% allocation; version currency tracked as a metric |
| Deprecation started but never finished | Two technologies for one concern, permanently — the worst outcome | Owner and date mandatory at step 3; stalled removals surfaced in review |
| Trial technologies become permanent without a decision | Unevaluated technology becomes load-bearing | End date set in advance; promote or remove, no third option |
| Abstraction added for lock-in avoidance becomes lowest-common-denominator | Capability lost to portability nobody uses | Thin, capability-based abstractions with explicit escape hatches |
| Team fit criterion used to reject anything unfamiliar | Stagnation disguised as pragmatism | Criterion 5 is about time-to-competence, not current familiarity |

## Dependencies

- **Depends on:** principles (`22`), architecture philosophy (`23`), current
  technology decisions (`06`).
- **Depended on by:** future evolution (`28`), security strategy (`09`, supply
  chain), review process (`16`).

## Future Improvements

- Publish the initial radar with current technologies placed in rings, as a
  living document reviewed at phase boundaries.
- Add automated reporting of dependency age and maintenance signals so criterion
  4 is monitored rather than assessed once.
- Record actual reversal costs when a technology is replaced, to calibrate future
  estimates — our present figures are informed guesses.
