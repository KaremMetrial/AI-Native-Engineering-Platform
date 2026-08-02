# Documentation Strategy

## Purpose

Define what documentation exists, who owns it, and how it stays current.
Documentation that is wrong is worse than documentation that is missing — a
reader who knows nothing investigates, while a reader who trusts a stale
document acts on it and is wrong with confidence.

## Scope

**In scope:** documentation types and their audiences, ownership, currency
mechanisms, structure and conventions, and documentation for AI agents.

**Out of scope:** end-user product documentation and marketing content, which
are product functions rather than engineering foundations.

---

## Principle

**Document decisions and intent. Generate reference material. Delete
everything else.**

The failure mode of engineering documentation is not too little — it is too much
of the wrong kind. Hand-written API references, step-by-step tutorials mirroring
the UI, and prose descriptions of what code does all decay within weeks and then
actively mislead.

Three categories, each with a different currency strategy:

| Category | Example | Currency strategy |
| --- | --- | --- |
| **Decisions and intent** | ADRs, this foundation set | Durable — records why, which does not change when code does |
| **Generated reference** | API contracts, schema docs, types | Always current — derived from source |
| **Operational** | Runbooks, onboarding | Verified by use — exercised regularly, so staleness surfaces |

Anything fitting none of these is a candidate for deletion.

---

## Documentation Types

### 1. Engineering foundation — `docs/`

This set. The architectural and strategic record: why the platform is built this
way.

- **Audience:** engineers, technical leadership, AI agents, technical due
  diligence.
- **Owner:** technical leadership.
- **Currency:** reviewed at each phase boundary; amended by PR when a decision
  changes.
- **Stability:** deliberately high. Frequent churn here signals unstable
  foundations rather than healthy iteration.

### 2. Architectural Decision Records — `docs/architecture/adr/`

One record per significant decision, appended over time.

- **Audience:** current and future engineers.
- **Owner:** decision author.
- **Currency:** **immutable once accepted.** A superseded ADR is marked
  superseded with a link forward; it is never edited.

**Why immutability matters:** the value of an ADR is the record of what was
known and believed *at the time*. Editing it to match current understanding
destroys exactly the information that makes it useful — the reasoning that led
to a decision that later needed changing. This is the same principle as the
Delivery Graph's versioning, applied to ourselves.

### 3. API contracts — `packages/contracts/`

OpenAPI specifications and generated types.

- **Owner:** the module owning the endpoint.
- **Currency:** always — the contract *is* the source (D-110), verified by
  contract tests. Drift is a build failure.

**Deliberately not hand-written.** Hand-maintained API documentation is
guaranteed to diverge, and diverged API docs cause integration bugs that take
hours to diagnose.

### 4. Code-level documentation

Docblocks on public interfaces stating purpose, invariants and failure modes.
Inline comments explaining **why** (D-135).

- **Currency:** enforced by review; a comment contradicting its code is a
  blocking review comment.
- **Not documented:** what the code does. That is the code's job, and a restated
  implementation goes stale the moment the implementation changes.

### 5. Module documentation — `README.md` per module

Short (under a page): the module's responsibility, its key concepts and
vocabulary, its published interface, and what it deliberately does *not* own.

**The "does not own" section is the most valuable part.** Boundary confusion is
the most common source of misplaced code in a modular monolith, and an explicit
statement of exclusions prevents more misplacement than any amount of
description.

### 6. Runbooks — `docs/operations/runbooks/`

One per alert (D-159): what the alert means, how to diagnose, how to mitigate,
how to escalate.

- **Currency:** verified by use during incidents and drills; updated in the
  post-incident review.
- **Requirement:** every alert has one, or the alert is deleted.

### 7. Onboarding — `docs/onboarding/`

Setup, architecture orientation, first-contribution walkthrough.

- **Currency:** **verified by the next person onboarded**, who fixes what they
  find broken as their first contribution. This is the only reliable mechanism —
  onboarding docs decay invisibly to everyone who is already onboarded.
- **Success metric:** M-5, new engineer to first merged PR in under three days.

### 8. AI agent context — `CLAUDE.md`

Entry point for AI coding agents: imports the charter, states repository
conventions.

- **Currency:** updated whenever standards or structure change.
- **Deliberately thin** — it imports the charter rather than restating it, so
  there is one source of truth (`CLAUDE.md`).

---

## Currency Mechanisms

Documentation rots by default. Each mechanism below attacks a specific decay
path:

| Mechanism | Attacks |
| --- | --- |
| Docs live in the repository beside the code | Documentation in a separate system is forgotten |
| Docs change in the same PR as the code | Prevents the "update the docs later" that never happens |
| Generated reference where possible | Removes the decay path entirely |
| Ownership is explicit | Unowned documentation is nobody's problem |
| Phase-boundary review | Catches accumulated drift in the foundation set |
| Onboarding verified by the next joiner | Catches decay invisible to existing staff |
| Runbooks exercised in drills | Catches procedures that no longer work |
| Immutable ADRs | Removes the temptation to rewrite history |

**Deletion is a first-class action.** Documentation that no longer serves a
reader is removed, not archived "just in case." Stale documents in a repository
are found by search and trusted by readers who have no way to know they are
obsolete.

---

## Structure and Conventions

### Document structure

Every document in this foundation set carries: **Purpose, Scope, Decisions,
Risks, Dependencies, Future Improvements.**

- **Purpose and Scope** let a reader determine relevance in seconds, and — as
  importantly — state what the document does *not* cover, preventing duplication
  across the set.
- **Decisions** are numbered globally (D-nn) so they can be referenced precisely
  from anywhere, including from code comments and ADRs.
- **Risks** keep known weaknesses visible instead of implicit. A foundation that
  lists no risks is not risk-free; it is dishonest.
- **Dependencies** make the reading order and the impact of change explicit.
- **Future Improvements** capture known gaps without polluting the code with
  TODOs (D-135) — deferred work stays visible in the document that owns it.

### Writing conventions

- Markdown, plain language, short sentences.
- **Every recommendation carries its reasoning.** A directive without a reason
  is followed until it is inconvenient, then abandoned; a directive with a
  reason can be evaluated, applied intelligently, and challenged with evidence.
- Trade-offs and rejected alternatives are stated. Documentation that presents
  only the chosen path implies no alternatives were considered, and invites
  relitigating the same debate every six months.
- Diagrams as text (Mermaid or ASCII) so they are diffable and reviewable.
  Binary diagrams are never updated because updating them requires a tool nobody
  has open.
- Absolute claims are avoided unless they are genuinely absolute — and where
  they are (tenant isolation, security gates), they are stated as absolutes
  deliberately.

---

## Documentation for AI Agents

A meaningful share of contribution will be AI-assisted, which changes what
documentation must do.

- **Documentation is agent context.** An agent reads `CLAUDE.md`, the charter
  and this set to understand constraints. Poor documentation produces poor
  AI-generated code, at volume.
- **Explicit constraints beat implicit convention.** A human infers conventions
  from surrounding code; an agent benefits enormously from the rule being
  stated. This is why `13` states rules explicitly rather than relying on
  "follow the existing style."
- **Reasoning matters more, not less.** An agent given a rule with its reason
  can apply it correctly in novel situations; an agent given a bare rule applies
  it literally and wrongly at the edges.
- **This set is written to be read by both.** The structure — explicit
  decisions, stated reasoning, named trade-offs — serves human and agent readers
  equally, which is a genuine convenience rather than a compromise.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-179 | Document decisions and intent; generate reference; delete the rest | Hand-written reference material decays and then misleads |
| D-180 | Documentation lives in the repository with the code | Separate systems are forgotten and drift |
| D-181 | Documentation changes in the same PR as the code | "Later" does not arrive |
| D-182 | ADRs are immutable once accepted; superseded, never edited | Their value is the record of what was believed at the time |
| D-183 | API documentation is generated from the contract, never hand-written | Guaranteed divergence otherwise |
| D-184 | Module READMEs state what the module does *not* own | Boundary confusion is the main cause of misplaced code |
| D-185 | Onboarding docs verified by the next person onboarded | The only mechanism that catches invisible decay |
| D-186 | Every alert has a runbook, or the alert is deleted | Unactionable alerts hide actionable ones |
| D-187 | Every recommendation carries its reasoning | Unreasoned directives are abandoned under pressure and misapplied by agents |
| D-188 | Diagrams as text, not binaries | Binary diagrams are never updated |
| D-189 | Deletion of stale documentation is a first-class action | Stale docs are found by search and trusted by readers |
| D-190 | Uniform section structure across the foundation set | Enables fast relevance assessment and prevents cross-document duplication |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Documentation drifts from implementation | Readers act on wrong information | Same-PR updates; generated reference; phase-boundary review |
| Foundation set becomes too large to read | Ignored; effectively absent | Structured for selective reading; scope sections state relevance; reference material excluded by design |
| Over-documentation crowds out signal | Maintenance burden; the important parts are lost | Delete aggressively; generate rather than write |
| Documentation written once and never revisited | Silent obsolescence | Explicit ownership; scheduled review at phase boundaries |
| ADR process abandoned as bureaucracy | Decision rationale lost — the exact problem the product exists to solve | ADRs kept short; required only for genuinely significant decisions |
| AI agents trained on stale documentation produce stale patterns | Systematic drift at volume | `CLAUDE.md` imports the single source of truth; standards are enforced mechanically regardless |

## Dependencies

- **Depends on:** the engineering charter; review process (`16`).
- **Depended on by:** onboarding (M-5); all engineering work.

## Future Improvements

- Add a link checker in CI to catch references to moved or deleted documents.
- Add documentation currency checks — flag documents unchanged across a phase
  whose subject code has changed substantially.
- Publish per-context glossaries once domain vocabulary stabilizes.
