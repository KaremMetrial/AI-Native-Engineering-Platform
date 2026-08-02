# Domain Model and DDD Tactical Patterns

## Purpose

Define the tactical domain-driven design patterns the platform uses: aggregate
boundaries, consistency rules, and the model of the Delivery Graph itself. `03`
defines the strategic decomposition into contexts; this document defines how the
model inside a context is built.

The domain model is the longest-lived artifact in the system (`23`) — it outlives
the framework, the infrastructure and the team. It therefore receives the most
design attention.

## Scope

**In scope:** aggregate design rules, consistency boundaries, the tactical
building blocks, the Delivery Graph model, and the key aggregates per context.

**Out of scope:** the physical schema (`36`), context relationships (`33`), and
class-level design.

---

## Why Aggregates Are the Central Decision

**An aggregate is a consistency boundary.** It defines what is guaranteed
consistent within one transaction and what is only eventually consistent. Every
other tactical decision follows from where those boundaries fall.

Get them too large and every write serializes against unrelated work, producing
lock contention and failed concurrent operations. Get them too small and
invariants that must hold cannot be enforced, producing corrupt state that no
amount of application code reliably prevents.

**The rule we apply:** an aggregate contains exactly what must be
transactionally consistent to protect a true business invariant — and nothing
else, however conceptually related.

---

## Aggregate Design Rules

### Rule 1 · One aggregate per transaction

**Decision.** A single transaction modifies exactly one aggregate instance.
Changes spanning aggregates are coordinated by events, accepting eventual
consistency between them.

**Reasoning.** Multi-aggregate transactions couple unrelated concerns into one
lock scope, so contention on a popular aggregate blocks work that has nothing to
do with it. Worse, it hides the fact that a design needs two aggregates to be
consistent — which is either a signal the boundary is wrong or a signal that the
consistency requirement is imagined rather than real.

Forcing the question "must these *really* be consistent at the same instant, or
merely converge?" almost always yields *converge*. Business processes are
eventually consistent by nature: an approval and the plan it triggers do not
happen in the same instant in the real world either.

**Alternatives.** *Allow multi-aggregate transactions* — simpler to write,
produces broad lock scopes and lets boundaries blur until aggregates are
meaningless. *Make everything one aggregate per tenant* — perfect consistency and
serializes every write in a tenant, which fails at any real concurrency.

**Trade-offs.** Eventual consistency must be visible in the UI (an approval
shows immediately; the regenerated plan appears shortly after) and must be
handled in tests. Some genuinely convenient operations become two steps.

**Benefits.** Contention is bounded to genuinely related work. Aggregates stay
small enough to reason about. The design surfaces false consistency requirements
early.

**Long-term impact.** This rule is what allows the model to scale from one tenant
to fifty thousand without redesign, and it is the rule most often abandoned under
delivery pressure — which is why it is stated as a rule rather than a preference.

### Rule 2 · Reference other aggregates by identity only

An aggregate holds the *ID* of another aggregate, never an object reference.

**Reasoning.** Object references invite traversal, traversal invites lazy
loading, lazy loading invites modifying the far object — and now one transaction
spans two aggregates, violating Rule 1 by accident rather than by decision.
Identity-only references make the boundary physically evident: you cannot modify
what you only hold an ID for.

It also keeps aggregates independently loadable, which is what makes them
independently cacheable and eventually independently storable.

### Rule 3 · Protect invariants inside; validate context outside

An aggregate enforces the invariants it owns and can check with its own state.
Anything requiring other aggregates' state is checked in the application layer
before the operation, accepting that the check is advisory rather than
transactionally guaranteed.

**Example.** "A requirement cannot be approved twice" is an invariant of the
requirement — enforced inside. "The project must be active" involves another
aggregate — checked in the application layer, where a race is possible and
acceptable.

**Reasoning.** Pretending cross-aggregate invariants are transactionally
guaranteed produces designs that are wrong under concurrency while appearing
correct. Being explicit about which guarantees are real is more honest and
produces better handling of the cases where they fail.

### Rule 4 · Aggregates are small by default

The default is one root entity plus its value objects. Child entities are added
only when they have their own lifecycle *and* must be transactionally consistent
with the root.

**The test:** if a child could be modified independently without violating an
invariant of the root, it is a separate aggregate.

---

## Tactical Building Blocks

| Pattern | Purpose | Rules |
| --- | --- | --- |
| **Entity** | Identity and lifecycle | Identity is immutable; equality by identity, never by attributes |
| **Value Object** | A concept defined by its attributes | Immutable; self-validating in the constructor; equality by value; **preferred over primitives** |
| **Aggregate Root** | Consistency boundary and the only entry point | External code reaches internals only through the root |
| **Domain Event** | A business-meaningful thing that happened | Immutable, past-tense, carries what consumers need without exposing internals |
| **Domain Service** | An operation that belongs to no single entity | Stateless; used only when the operation genuinely spans entities |
| **Repository** | Collection-like access to aggregates | **One per aggregate root.** Interface in Domain, implementation in Infrastructure |
| **Factory** | Complex construction | Used when construction has rules of its own; a constructor otherwise |
| **Specification** | Reusable, composable business predicate | Used where the same rule is needed for validation, querying and selection |

**On value objects specifically.** Primitive obsession — passing `string $status`,
`float $effort`, `string $tenantId` — is the most common cause of defects that
type systems should have caught. `EffortRange` validates that its minimum does
not exceed its maximum, at construction, once. A pair of floats validates
nothing, everywhere, forever. This is a deliberate bias, and it is the tactical
pattern with the highest return in a long-lived model.

**On domain services.** Genuinely useful for operations like "calculate a
risk-adjusted estimate from these drivers," which belongs to no single entity.
Frequently abused as a home for logic that belongs on an entity, producing
anaemic models where entities are data bags and all behaviour sits in services.
Reviewers check specifically for this — the smell is a service whose methods all
take the same entity as their first parameter.

---

## The Delivery Graph Model

The kernel (`03`), and the most consequential modelling decision in the platform.

### Model

```
   ┌──────────────────┐         ┌────────────────────────┐
   │    Artifact      │ 1     n │    ArtifactVersion     │
   │  (agg. root)     │────────▶│    (entity, immutable) │
   │                  │         │                        │
   │ id               │         │ id                     │
   │ tenantId         │         │ artifactId             │
   │ projectId        │         │ versionNumber          │
   │ type             │         │ content / contentRef   │
   │ currentVersionId │         │ createdAt, createdBy   │
   │ status           │         │ lineage ◀──────────────┼── VALUE OBJECT
   └──────────────────┘         └───────────┬────────────┘   model, promptVersion,
                                            │                inputVersionIds,
                                            │                tokens, cost
                    ┌───────────────────────┴──────────┐
                    │                                  │
        ┌───────────▼────────────┐        ┌────────────▼───────────┐
        │    ArtifactLink        │        │      Approval          │
        │    (agg. root)         │        │    (agg. root)         │
        │                        │        │                        │
        │ id, tenantId           │        │ id, tenantId           │
        │ fromVersionId          │        │ artifactVersionId ◀────┼── binds to a
        │ toVersionId            │        │ approvedBy, approvedAt │   VERSION,
        │ linkType               │        │ decision, comment      │   never an
        │ createdAt, createdBy   │        │                        │   artifact
        └────────────────────────┘        └────────────────────────┘
```

### Why the graph is not one aggregate

**Decision.** `Artifact`, `ArtifactLink` and `Approval` are three separate
aggregates. The graph as a whole is not an aggregate.

**Reasoning.** Modelling the graph as one aggregate — the intuitive choice, since
it is conceptually one connected structure — would mean every write anywhere in a
tenant's graph serializes against every other. A tenant with ten people working
on five projects would experience constant write conflicts on a single
aggregate. It would also mean loading a consistency boundary containing millions
of nodes, which is not loadable at all.

Separating them means a link can be created concurrently with a version being
added elsewhere, which is what the actual usage pattern requires.

**Alternatives.** *Graph as one aggregate* — perfect referential consistency,
unusable concurrency. *Artifact aggregate owning its outgoing links* — appealing,
and links are bidirectional in meaning (impact analysis traverses both ways),
so ownership by one side is arbitrary and makes reverse traversal awkward.
*Links as value objects inside versions* — makes a link immutable with its
version, and a link is created *after* both endpoints exist, so this ordering
does not work.

**Trade-offs.** Referential integrity between links and versions is not
transactionally guaranteed by the aggregate boundaries — a link could in
principle reference a version that was never committed. Handled by database
foreign keys, which enforce it at the storage layer without expanding the
aggregate.

**Benefits.** Concurrent work across a tenant's graph. Aggregates small enough to
load and reason about. Traversal is a query concern rather than an aggregate
concern.

**Long-term impact.** This decision determines whether the graph — the core
product asset — supports a team working concurrently or becomes a bottleneck that
forces users to work elsewhere, which is exactly the BR-1 failure mode.

### Immutability and versioning

- **`ArtifactVersion` is immutable.** An edit creates a new version. There is no
  update path.
- **`Artifact` is a thin mutable pointer** to the current version plus stable
  identity. Nearly all the data lives in versions.
- **Links reference versions, not artifacts.** This is what makes impact analysis
  precise: "derived from the *approved* version" is distinguishable from
  "derived from a superseded draft." Linking artifact-to-artifact would lose
  exactly the information that makes the graph valuable.
- **Approval binds to a version** (D-278). A floating approval would mean a
  contract was approved against text nobody read.

**Lineage is a value object on the version**, not a separate entity: it has no
identity or lifecycle of its own, is immutable with the version it describes, and
is meaningless apart from it.

### Link types

| Type | Meaning | Example |
| --- | --- | --- |
| `derives_from` | Produced using this as input | SRS derives from BRD |
| `satisfies` | Fulfils this requirement | Design satisfies requirement |
| `implements` | Realizes this specification | Task implements requirement |
| `verifies` | Tests this | Test case verifies acceptance criterion |
| `supersedes` | Replaces this version | Version 3 supersedes version 2 |
| `references` | Non-derivational mention | Architecture references a standard |

**A closed set, deliberately.** An open vocabulary of link types would make
traversal semantics unknowable — impact analysis must know which edges represent
dependency and which are informational. New types require an ADR, because each
one changes what traversal means.

---

## Key Aggregates by Context

Initial model. Each context refines its own during design; the point here is the
boundary pattern.

### Requirements

| Aggregate | Root | Contains | Key invariant |
| --- | --- | --- | --- |
| `RequirementDocument` | Document | Requirement entities, structure | Cannot be approved while any requirement is incomplete |
| `Requirement` | Requirement | Acceptance criteria (VOs) | Cannot transition to approved twice |
| `ChangeRequest` | Change request | Proposed modifications | Cannot apply to a superseded version |

`Requirement` is separate from `RequirementDocument` because individual
requirements are edited, approved and traced independently — a document-level
aggregate would serialize all editing within a document.

### Estimation

| Aggregate | Root | Contains | Key invariant |
| --- | --- | --- | --- |
| `Estimate` | Estimate | Line items, `EffortRange` VOs, driver VOs | Total must equal the sum of line items; range minimum ≤ maximum |
| `CalibrationModel` | Model | Historical coefficients | Only calibrates from completed projects |

`Estimate` holds its line items because the total-equals-sum invariant is real
and must hold transactionally — this is a case where a child entity genuinely
belongs inside.

### Commercial

| Aggregate | Root | Key invariant |
| --- | --- | --- |
| `Proposal` | Proposal | Cannot be sent without an approved estimate reference |
| `Contract` | Contract | Cannot be countersigned before all parties have signed |

### Planning · Quality · Design

| Aggregate | Key invariant |
| --- | --- |
| `WorkBreakdown` | Task effort must reconcile with the estimate it derives from |
| `Sprint` | Committed capacity cannot exceed available capacity |
| `TestPlan` | Every acceptance criterion has at least one covering case |
| `ArchitectureDecision` | An accepted ADR is immutable (D-182) |

### Identity and Tenancy

| Aggregate | Key invariant |
| --- | --- |
| `Tenant` | Must always have at least one owner |
| `Membership` | Role assignment valid only for an active tenant and user |
| `StakeholderGrant` | Must have an expiry; scope is a specific resource (D-60, D-61) |

---

## Consistency Model

| Scope | Guarantee | Mechanism |
| --- | --- | --- |
| Within an aggregate | Strong, transactional | Database transaction |
| Across aggregates in one context | Eventual | Domain events, same process |
| Across bounded contexts | Eventual | Integration events via outbox (`35`) |
| Graph link creation | Eventual, FK-enforced | Event-driven, with referential integrity at storage |
| Read models and analytics | Eventual, seconds | Event projections |
| Search and vector indexes | Eventual, seconds to minutes | Async indexing |

**Where eventual consistency is user-visible, the UI states it** — "plan updating"
rather than silently showing stale data. Hiding eventual consistency produces the
worst outcome: users see stale data, assume it is current, and act on it.

**Concurrency control** is optimistic: aggregates carry a version, and a
conflicting write fails and is retried or surfaced. Pessimistic locking is
reserved for the rare genuinely contended operation, because holding locks across
user think-time is how systems deadlock.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-330 | One aggregate modified per transaction | Multi-aggregate transactions broaden lock scope and hide wrong boundaries |
| D-331 | Aggregates reference each other by identity only | Object references invite traversal, which violates Rule 1 by accident |
| D-332 | Cross-aggregate checks are advisory, and said to be | Pretending they are transactional produces designs wrong under concurrency |
| D-333 | Aggregates default to a root plus value objects | Child entities require both independent lifecycle and a shared invariant |
| D-334 | Value objects strongly preferred over primitives | Primitive obsession is the top cause of defects a type system should catch |
| D-335 | The Delivery Graph is three aggregates, not one | One graph aggregate would serialize all writes per tenant and be unloadable |
| D-336 | `ArtifactVersion` is immutable; edits create versions | Lineage is only defensible if versions cannot change |
| D-337 | Links reference versions, not artifacts | Preserves the distinction between approved and superseded inputs — the graph's core value |
| D-338 | Link types are a closed set; additions require an ADR | Traversal semantics must be knowable; each type changes what impact analysis means |
| D-339 | Lineage is a value object on the version | No independent identity or lifecycle |
| D-340 | Optimistic concurrency by default | Pessimistic locks across user think-time cause deadlocks |
| D-341 | User-visible eventual consistency is shown, never hidden | Silent staleness leads users to act on stale data |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Aggregates grow as features are added | Lock contention; concurrency failures | Aggregate size reviewed when child entities are proposed; the independence test applied |
| One-aggregate-per-transaction abandoned under pressure | Boundaries blur until aggregates are meaningless | Stated as a rule; reviewed on any transaction spanning repositories |
| Anaemic model — logic drifts into services | Entities become data bags; rules scatter and duplicate | Reviewers check for services whose methods all take the same entity |
| Eventual consistency confuses users | Perceived data loss or bugs | Explicitly surfaced in the UI; latency budgets on projections |
| Link type vocabulary grows informally | Traversal semantics become unknowable | Closed set enforced by the type system; ADR required |
| Graph aggregate boundaries prove wrong under real usage | Rework in the core model | Concurrency tested with realistic multi-user scenarios in Phase 1 |

## Dependencies

- **Depends on:** bounded contexts (`03`), architecture (`05`), components
  (`31`), naming (`25`).
- **Depended on by:** context map (`33`), events (`35`), data architecture
  (`36`).

## Future Improvements

- Run event-storming sessions per context before its first aggregate is
  implemented; the model above is a starting hypothesis.
- Publish per-context aggregate diagrams with invariants stated explicitly.
- Validate the graph aggregate boundaries under concurrent multi-user load in
  Phase 1, before the model is depended upon by later contexts.
