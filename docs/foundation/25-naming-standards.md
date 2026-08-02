# Naming Standards

## Purpose

Establish naming as a first-class engineering concern. Names are the most
durable interface in the system — a badly named database column or API field
outlives the framework, the infrastructure and usually the team. This document
defines the vocabulary discipline, the conventions per artifact, and the rules
for changing a name once it exists.

`11` lists the mechanical conventions. This document establishes the reasoning
and covers the cases that mechanics cannot decide.

## Scope

**In scope:** ubiquitous language, cross-layer naming consistency, conventions
for every named artifact, anti-patterns, and the rename policy.

**Out of scope:** code formatting (`13`) and file placement (`11`).

---

## Why Naming Warrants a Document

Naming is treated as a matter of taste in most codebases, which is why most
codebases have four words for the same concept and one word for four different
concepts. Three consequences make it a foundation-level concern here:

1. **Names are the primary interface to meaning.** An engineer reading unfamiliar
   code has the names and nothing else. Reading is where the decade of cost
   lives (P4), and names dominate reading comprehension.
2. **Some names are effectively permanent.** A class can be renamed in seconds.
   A database column, a public API field, an event payload key or a persisted
   enum value cannot — those require a migration, a deprecation cycle, and
   coordination with consumers (`27`). The cost of a bad name is set at the
   moment it is chosen.
3. **This product is about vocabulary.** We are building a system whose purpose
   is to keep meaning intact from a client conversation to a deployed feature. A
   codebase whose own vocabulary is inconsistent would be a poor advertisement
   for it.

---

## Ubiquitous Language

**Decision.** The domain vocabulary used by the business is the vocabulary used
in code, database, API, events and UI. Where they differ, one of them is wrong
and the difference is resolved rather than translated.

**Reasoning.** Every translation layer between business language and code
language is a place where meaning is lost and misunderstanding accumulates. When
a business expert says "engagement" and the code says `Project`, every
conversation between them requires a mental mapping — and mappings are performed
inconsistently, especially under pressure, especially by newcomers. The bug
reports that begin "you built the wrong thing" almost always trace back to a
vocabulary mismatch nobody noticed.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| Technical naming independent of the domain | Frees engineers to name for implementation clarity. Rejected: it guarantees a permanent translation tax and disconnects the code from the requirements it implements. |
| Translation layer between domain and technical terms | Explicit and honest about the mismatch. Rejected: it doubles the vocabulary and someone must maintain the mapping forever. |
| Business adopts the engineering vocabulary | Occasionally correct when the engineering term is genuinely more precise. Not a default — the business owns the domain. |

**Trade-offs.** Domain terms are sometimes longer, occasionally ambiguous, and
sometimes collide with language keywords. We accept the friction. Establishing
the vocabulary also requires real conversation with domain experts, which costs
time up front.

**Benefits.** Requirements map to code without translation. Domain experts can
read test names and understand them. New engineers learn one vocabulary rather
than two.

**Long-term impact.** Vocabulary drift is a slow, invisible failure. Ten years of
small divergences produce a codebase where nobody is certain whether `Client`,
`Customer` and `Account` are the same thing — and by then, finding out requires
archaeology.

### Context-local vocabulary

**The same word may legitimately mean different things in different bounded
contexts, and this is a feature, not a defect.**

A "requirement" in Requirements is a versioned specification with acceptance
criteria. In Estimation it is a cost driver with a complexity weight. In Quality
it is something to verify. Forcing one shared definition would produce a bloated
model serving none of them well — this is precisely the coupling that bounded
contexts exist to prevent.

**Rules:**

- Within a context, one word means exactly one thing.
- Across contexts, the same word may differ — and each context's glossary states
  its own definition.
- **Translation happens explicitly at the boundary**, in the anti-corruption
  logic of the consuming context, never implicitly by assuming the words match.
- Where a word means genuinely different things, that difference is evidence the
  boundary is in the right place (D-223).

---

## Cross-Layer Consistency

**Decision.** One concept carries one name across every layer it appears in.

A requirement's title is `title` in the domain object, `title` in the database
column, `title` in the API response, `title` in the event payload, and "Title"
in the UI. Not `name`, `requirement_title`, `reqTitle` and `label` in four
places.

**Reasoning.** Cross-layer renaming forces every reader to maintain a mental
translation table, and every mapping site is a place for a defect. Debugging a
field that changes name three times between the database and the browser is
gratuitously hard, and the difficulty is entirely self-inflicted.

**Alternatives.** *Layer-idiomatic naming* — each layer names things the way its
ecosystem prefers; produces the translation table described above, and every
mapping site becomes a defect site. *Automatic mapping conventions* —
`requirement_title` ↔ `requirementTitle` by rule; works until one layer needs a
name the rule cannot produce, at which point the exception is invisible.
*Generated contracts as the only source* — we do this for the API layer (D-110),
which is why that boundary is reliable; it does not extend to database or event
naming.

**Trade-offs.** Layer-specific conventions must yield: casing adapts to each
layer's convention (`snake_case` in the database, `camelCase` in TypeScript) but
the *word* does not change. Occasionally a domain term is awkward in one layer,
and we keep it anyway.

**Benefits.** A single grep finds every occurrence of a concept across the entire
stack — the cheapest and most-used debugging technique available. Mapping code
becomes mechanical and reviewable rather than a place where meaning changes.

**Long-term impact.** This is what makes a system traceable end to end by
searching for a single word — the cheapest and most-used debugging technique
there is.

---

## Conventions by Artifact

Mechanical conventions are in `11`. These are the ones requiring judgment.

### Domain

| Artifact | Convention | Example |
| --- | --- | --- |
| Entity | Singular domain noun | `Requirement`, `Estimate` |
| Value object | The concept, not its type | `EffortRange`, not `EstimateFloat` |
| Aggregate root | The concept the invariant protects | `RequirementDocument` |
| Domain event | Past tense; states what happened | `RequirementApproved` |
| Command / use case | Imperative; states intent | `ApproveRequirement` |
| Query | `Get`/`List`/`Find` + what | `ListPendingApprovals` |
| Domain exception | The rule violated, not the failure | `RequirementAlreadyApproved` |
| Repository | Entity + `Repository` | `RequirementRepository` |
| Policy | Resource + `Policy` | `RequirementPolicy` |

**Events are named for what happened, not what should happen next.**
`RequirementApproved`, never `NotifyPlanningOfApproval`. An event named for its
consumer couples the producer to that consumer — exactly the coupling events
exist to remove (D-34) — and becomes wrong the moment a second consumer appears.

**Exceptions name the violated rule.** `RequirementAlreadyApproved` tells a
reader what went wrong. `InvalidStateException` tells them nothing and forces
them into the stack trace.

### Data

| Artifact | Convention | Notes |
| --- | --- | --- |
| Table | `snake_case` plural | `requirement_versions` |
| Column | `snake_case`, matching the domain property | No table-name prefix — `requirements.title`, not `requirements.requirement_title` |
| Foreign key | `<singular_referenced>_id` | `requirement_id` |
| Boolean column | `is_`/`has_` prefix, always positive | `is_approved`, never `is_not_draft` |
| Timestamp | `<past_participle>_at` | `approved_at`, `created_at` |
| Index | `idx_<table>_<columns>` | Tenant-scoped indexes lead with `tenant_id` |
| Constraint | `<type>_<table>_<detail>` | `chk_requirements_status` |
| Enum value | `snake_case`, semantic | `pending_approval`, never `2` |

**Negated booleans are prohibited.** `is_not_archived` produces double negatives
at every call site (`if (!isNotArchived)`), which is a reliable source of
inverted-logic defects. Name the positive.

**Persisted enum values are semantic strings, never integers.** An integer enum
is unreadable in the database, breaks silently when the code's ordering changes,
and makes every production query require a lookup table held in someone's head.
The storage saving is irrelevant; the readability cost is permanent.

### API

| Artifact | Convention | Example |
| --- | --- | --- |
| Resource path | `kebab-case`, plural nouns | `/api/v1/requirement-versions` |
| Field | `camelCase`, matching the domain | `approvedAt` |
| Query parameter | `camelCase` | `?includeArchived=true` |
| Error code | `SCREAMING_SNAKE`, stable | `REQUIREMENT_ALREADY_APPROVED` |
| Action on a resource | Sub-resource or verb suffix, sparingly | `POST /requirements/{id}:approve` |

**Paths are nouns; HTTP methods are the verbs.** `POST /requirements/{id}/approve`
is acceptable where an action does not map to CRUD; `POST /approveRequirement` is
not. RPC-shaped URLs discard the entire benefit of resource modelling.

**Error codes are part of the contract and are versioned like fields** (`27`).
Clients branch on them, so renaming one is a breaking change.

### Events and messages

| Element | Convention |
| --- | --- |
| Event type | `<Context>.<Entity><PastTenseVerb>` — `Requirements.RequirementApproved` |
| Payload field | `camelCase`, matching the domain |
| Envelope field | Fixed: `eventId`, `eventType`, `eventVersion`, `occurredAt`, `tenantId`, `correlationId` |

**Context-qualified event names** prevent collision as the number of contexts
grows, and make the producer obvious to anyone reading a subscription.

### Operational

| Artifact | Convention | Example |
| --- | --- | --- |
| Feature flag | `<context>.<feature>` | `requirements.ai_review` |
| Metric | `<domain>.<subject>.<unit>` | `requirements.generation.duration_ms` |
| Trace span | `<component>.<operation>` | `ai.brd_synthesis` |
| Log field | `camelCase`, consistent across services | `tenantId`, `correlationId` |
| Environment variable | `SCREAMING_SNAKE`, prefixed | `PLATFORM_DB_HOST` |
| Queue / job | `<Context><Action>Job` | `RequirementsGenerateBrdJob` |

**Metric names are effectively permanent** once dashboards and alerts depend on
them. Renaming one silently breaks every alert that references it — including
alerts nobody has looked at in two years, which will now never fire.

### Tests

Test names describe behaviour, not method names:

- Good: `approving an already-approved requirement is rejected`
- Bad: `testApproveRequirement2`

**Reasoning.** A failing test's name is the first and often only diagnostic a
reader gets. It should state the broken expectation. Behaviour-named tests also
survive refactoring — they describe what the system does, not how it is
currently structured (P9, and `14`'s behaviour-over-implementation principle).

### AI artifacts

| Artifact | Convention | Example |
| --- | --- | --- |
| Workflow | `<capability>_<action>` | `brd_synthesis`, `requirement_extraction` |
| Prompt file | `<workflow>/<role>.v<n>` | `brd_synthesis/system.v3` |
| Golden set case | `<workflow>/<scenario>` | `brd_synthesis/sparse_discovery_input` |
| Evaluation rubric | `<workflow>_<dimension>` | `brd_synthesis_groundedness` |

Prompt versions are part of the name because every generation records which
version produced it (D-93) — the name *is* the lineage reference.

---

## Anti-Patterns

Each of these is prohibited, with the reason, because a prohibition without a
reason gets rediscovered and re-argued.

| Anti-pattern | Why prohibited |
| --- | --- |
| `Manager`, `Helper`, `Util`, `Processor`, `Handler` (as the whole name) | Names that describe nothing. They become dumping grounds because anything can be justified as belonging there. |
| `Data`, `Info`, `Object`, `Item` suffixes | `RequirementData` is not distinguishable from `Requirement`. If two types are needed, the difference should be the name. |
| Abbreviations | `req`, `est`, `usr` save keystrokes once and cost comprehension forever. Exceptions: universally understood (`id`, `url`, `api`, `http`). |
| Type in the name | `requirementList`, `titleString` — the type is in the type system. It also lies when the type changes. |
| Negated booleans | Produces double negatives at call sites; a reliable source of inverted-logic defects. |
| Integer enum values in storage | Unreadable in the database, order-dependent, silently wrong after a code change. |
| Names encoding implementation | `cachedRequirementRepository`, `postgresEstimateStore` — leaks the mechanism into every consumer and lies when it changes. |
| Sequential names | `Handler2`, `RequirementServiceNew` — always the residue of a migration nobody finished. |
| Inconsistent synonyms | `delete`/`remove`/`destroy` for the same operation. Pick one per codebase. Ours is `delete`. |
| Context-free names in a shared namespace | `Status`, `Type`, `Item` at the top level. Qualify or scope them. |

**On `Service`:** permitted only where it is genuinely a domain service — a
domain operation that does not belong to a single entity. It is not permitted as
a default suffix for "a class that does things," which is how it becomes a
synonym for `Manager`.

---

## The Rename Policy

**Decision.** Renaming is encouraged where it is cheap and governed where it is
not. The cost is determined by who depends on the name.

| Scope | Cost | Policy |
| --- | --- | --- |
| Local variable, private method | Trivial | Rename freely; no discussion |
| Internal class, module-private type | Low | Rename freely; the compiler finds every usage |
| Cross-module published interface | Moderate | Normal review; coordinate within the change |
| Database column | High | Expand-contract migration (D-120) |
| Public API field or error code | High | Deprecation cycle (`27`) |
| Event type or payload field | Very high | Additive-only evolution; old consumers must keep working |
| Metric or log field | Moderate, deceptive | Breaks dashboards and alerts silently — coordinate explicitly |

**Reasoning.** The instinct to leave a bad name alone because "renaming is risky"
is correct for the bottom half of this table and wrong for the top half. Cheap
renames should happen constantly — a name that has drifted from its meaning is
an active source of misunderstanding, and fixing it costs minutes.

**The corollary is the important part: because the bottom rows are expensive,
those names deserve disproportionate care when first chosen.** A column name is
a ten-year decision made in five seconds. Reviewers should weight schema, API and
event naming far more heavily than internal naming — the opposite of where review
attention naturally goes.

**Alternatives.** *Never rename* — avoids all churn and permanently entrenches
every early mistake, including ones made before the domain was understood.
*Rename freely everywhere* — treats a column rename as equivalent to a variable
rename, and breaks consumers. *Rename only during major versions* — batches
renames into large, risky changes and leaves bad names in place for years.

**Trade-offs.** Frequent internal renaming produces larger diffs and some merge
friction. Worth it; renames are mechanical and reviewable.

**Benefits.** Vocabulary tracks understanding as the domain is learned, rather
than freezing at the point of maximum ignorance. And directing review attention
to the expensive rows (D-253) means the names that cannot be fixed get the
scrutiny they deserve.

**Long-term impact.** Codebases where names are never fixed accumulate a layer of
misleading vocabulary that must be learned and worked around by everyone,
forever. Codebases where names are fixed continuously stay legible.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-243 | Domain vocabulary is the code vocabulary; mismatches are resolved, not translated | Every translation layer loses meaning and taxes every conversation |
| D-244 | A word may mean different things in different contexts; each context publishes its glossary | Forcing one definition produces a bloated model serving none of them |
| D-245 | Boundary translation is explicit, never assumed | Same word, different meaning, is exactly where silent defects enter |
| D-246 | One concept, one name across all layers; only casing adapts | Cross-layer renaming forces a mental translation table and adds defect sites |
| D-247 | Events named for what happened, never for their consumer | Consumer-named events recouple producer to consumer |
| D-248 | Exceptions name the violated rule | A reader should not need the stack trace to know what went wrong |
| D-249 | Booleans always positive; negated names prohibited | Double negatives at call sites cause inverted-logic defects |
| D-250 | Persisted enums are semantic strings, never integers | Integer enums are unreadable and silently order-dependent |
| D-251 | Tests named for behaviour, not method | The name is the primary diagnostic and survives refactoring |
| D-252 | Rename policy graded by dependent scope | Cheap renames should be constant; expensive ones governed |
| D-253 | Schema, API, event and metric names get disproportionate review weight | They are ten-year decisions typically made in seconds |
| D-254 | Prompt version is part of the prompt's name | The name is the lineage reference recorded on every generation |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Vocabulary drifts as the domain is better understood | Code and business diverge silently over years | Per-context glossaries reviewed at phase boundaries; renames encouraged where cheap |
| Cross-layer consistency abandoned under delivery pressure | Translation tax returns permanently | Contract generation from one source (D-110) makes the API layer automatic |
| Anti-patterns reappear via AI-generated code | Common patterns include common bad names | Standards stated explicitly for agent context (`18`); review checks naming as judgment (D-162) |
| Expensive names chosen carelessly early | Permanent bad vocabulary in schema and API | D-253 directs review attention there specifically |
| Rename policy read as "renaming is risky" | Bad names never fixed anywhere | Policy is explicitly graded; the top rows encourage renaming |
| Context-local vocabulary used to justify genuine inconsistency | Two names for one concept inside one context | Within a context the rule is absolute: one word, one meaning |

## Dependencies

- **Depends on:** module boundaries (`03`), repository conventions (`11`),
  coding standards (`13`), principles (`22`).
- **Depended on by:** versioning strategy (`27`, contract naming), review
  process (`16`), documentation strategy (`18`, glossaries).

## Future Improvements

- Publish per-context glossaries as the first artifact of each context's design,
  before its first entity is written.
- Add automated checks for the mechanically detectable anti-patterns — negated
  booleans, type-in-name suffixes, prohibited generic suffixes.
- Add a naming section to the ADR template for decisions that introduce
  long-lived vocabulary.
