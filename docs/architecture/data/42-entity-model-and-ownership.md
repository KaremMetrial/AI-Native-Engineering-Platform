# Entity Model, Relationships and Data Ownership

## Purpose

Define the entities in each bounded context, how they relate, and — critically —
which context owns which data. Ownership is the decision that determines whether
module boundaries survive contact with a shared database.

`32` defines the aggregates and consistency rules; this document is the entity
and ownership map they imply.

## Scope

**In scope:** entity catalogue per context, relationship rules, cross-context
reference policy, the ownership matrix, and shared-kernel entities.

**Out of scope:** physical schema and column types (implementation), aggregate
design rules (`32`), storage layout (`36`).

---

## The Ownership Rule

**Decision.** Every table has exactly one owning bounded context. Only the owner
writes it, and only the owner reads it directly; all other access goes through
the owner's Application layer or through published events.

**Reasoning.** A shared database makes it trivially easy to `JOIN` across module
boundaries. That single convenience is how modular monoliths become
unextractable: once three contexts read `requirements` directly, the Requirements
context can no longer change its schema, and extracting it later means finding
every query anywhere that touches its tables.

Ownership makes the boundary a property of the *data*, not merely of the code
layout. Combined with the enforcement in `33`, it is what keeps extraction a
directory-and-connection change rather than an archaeology project.

**Alternatives considered.**

| Alternative | Why not |
| --- | --- |
| **Shared schema, free access** | Fastest to develop; destroys every boundary and forecloses extraction. The default outcome without an explicit rule. |
| **Schema per context in one database** | Stronger signal and enforceable by grants. Genuinely attractive — rejected because cross-context reporting and the shared kernel need cross-schema access anyway, and per-schema grants complicate the RLS role model (`07`) that we cannot afford to complicate. Revisit if boundary violations prove hard to prevent by static analysis alone. |
| **Database per context** | Complete isolation; distributed transactions, no shared kernel, and 15 databases to operate at a scale that does not need it |

**Trade-offs.** Cross-context queries require an application call or a read model
rather than a `JOIN` — measurably slower and more code for a report that a single
query could have produced. Accepted deliberately: `43` provides read models for
exactly these cases.

**Benefits.** Contexts refactor their schemas freely. Extraction stays cheap.
Reporting cannot silently couple everything to everything.

**Long-term impact.** This is the rule that determines whether the modular
monolith is genuinely modular in year five or merely organized into folders.

---

## Cross-Context References

**Decision.** Foreign keys are used freely *within* a context and to the shared
kernel. **Cross-context references are stored as plain identifiers with no
foreign key**, validated at the application layer and reconciled asynchronously.

**Reasoning.** A foreign key from `planning.tasks` to `requirements.requirements`
seems like free integrity, and it costs three things:

1. **It forecloses extraction.** A cross-context FK becomes a distributed
   referential constraint the moment either context moves — which cannot be
   enforced at all, so the constraint must be dropped under time pressure during
   a migration.
2. **It invites boundary violations.** An FK is an invitation to `JOIN`, and a
   `JOIN` is a direct read of another context's table.
3. **Cascade semantics cross boundaries unpredictably.** `ON DELETE CASCADE`
   from one context silently destroying another context's rows is a defect
   waiting to happen, and the cascade is invisible in the deleting context's
   code.

**What we give up:** database-enforced referential integrity across contexts.
Handled by validating the reference exists when it is created, reacting to
deletion events, and running daily reconciliation to detect dangling references
(`41`) — which converts a hard guarantee into a detected-and-repaired one.

**Alternatives.** *Cross-context FKs* — real integrity, at the three costs above.
*No stored references at all, events only* — maximum decoupling, and every
"which requirement does this task implement?" query becomes an event replay.

**Trade-offs.** Dangling references become possible. Accepted because they are
detectable, rare, and repairable — whereas an unextractable monolith is neither.

**Long-term impact.** The single technical difference between a modular monolith
that can be split and one that cannot.

### Reference rules

| Reference | Mechanism |
| --- | --- |
| Within a context | Foreign key, with cascade rules where correct |
| To the shared kernel (`tenants`, `artifacts`) | Foreign key — the kernel is stable and universally depended upon |
| Across contexts | **Plain identifier, no FK**, validated on write |
| Artifact-to-artifact, semantic | **Delivery Graph link** (`32`) — never a direct FK |
| To an external system's entity | Anticorruption-layer mapping table in Integrations (`33`) |

**The third and fourth rows are the interesting pair.** A task implementing a
requirement is a *semantic* relationship that belongs in the Delivery Graph as a
typed link — it is traceability, which is the product. A task's `project_id` is a
*structural* reference that is not traceability and should not clutter the graph.
Distinguishing them keeps the graph meaningful rather than a dumping ground for
every foreign key in the system.

---

## Shared Kernel Entities

Owned jointly, changed only by ADR (`33`).

| Entity | Purpose | Key attributes |
| --- | --- | --- |
| `Tenant` | The isolation boundary | id, name, status, plan, region, created_at |
| `User` | A person's platform identity | id, email, status, created_at |
| `Membership` | A user's role within a tenant | tenant_id, user_id, role, status |
| `Project` | The primary work-scoping unit | id, tenant_id, name, status, lifecycle_stage |
| `Artifact` | Delivery Graph node identity | id, tenant_id, project_id, type, current_version_id, status |
| `ArtifactVersion` | Immutable content version | id, artifact_id, version_number, content/content_ref, lineage, created_by |
| `ArtifactLink` | Typed semantic edge | id, tenant_id, from_version_id, to_version_id, link_type |
| `Approval` | Version-bound approval | id, tenant_id, artifact_version_id, approved_by, decision |

**`Project` is in the kernel deliberately**, despite being conceptually a
Discovery or Planning concern. Nearly every context scopes by project, and
routing that through a context boundary would add a call to almost every
operation. It is kept minimal — identity, name, status — with rich
project-related state living in the contexts that own it.

**This is the shared kernel in full.** Eight entities. Its smallness is the
entire reason a shared kernel is safe here (`33`).

---

## Entity Catalogue by Context

Key entities per context. Not exhaustive — the point is ownership and
relationships, not a schema.

### C1 · Identity and Tenancy

| Entity | Notes |
| --- | --- |
| `Tenant`, `User`, `Membership` | Shared kernel (above) |
| `Session` | Active sessions; revocation checked per request (`39`) |
| `Role`, `Permission`, `RoleAssignment` | RBAC model |
| `PolicyRule` | ABAC attribute rules (`07`) |
| `StakeholderGrant` | External access: resource, expiry, revocation (D-60) |
| `IdentityProvider` | Per-tenant SSO configuration |
| `ApiCredential` | Tenant-scoped integration credentials, field-encrypted |

### C2 · Discovery

`DiscoverySession`, `Question`, `Response`, `Assumption`, `Constraint`,
`StakeholderInput`, `UploadedDocument` (reference to blob, untrusted-labelled).

### C3 · Requirements

`RequirementDocument`, `Requirement`, `AcceptanceCriterion`,
`RequirementRelation` (within-document), `ChangeRequest`, `TraceabilityEntry`.

### C4 · Design

`ArchitectureDocument`, `DesignDecision` (ADR-shaped), `DataModelDesign`,
`ApiContractDesign`, `TechnologySelection`, `NonFunctionalTarget`.

### C5 · Estimation

`Estimate`, `EstimateLineItem`, `EffortRange` (VO), `ComplexityDriver`,
`CalibrationModel`, `HistoricalActual` (fed by `Execution.WorkCompleted`).

### C6 · Commercial

`Proposal`, `ProposalSection`, `PricingModel`, `Contract`, `ContractClause`,
`SignatureRequest`, `SignatureEvent`.

**Highest confidentiality class** (`09`) — a separate audience (P8) and separate
access rules, which is why it is its own context.

### C7 · Planning

`WorkBreakdown`, `Task`, `TaskDependency`, `Sprint`, `SprintCommitment`,
`Milestone`, `CapacityAllocation`, `ReplanEvent`.

### C8 · Execution

`WorkItem`, `CodeReference` (branch, PR, commit — via Integrations),
`TechnicalNote`, `WorkLog`.

### C9 · Quality

`TestPlan`, `TestCase`, `TestExecution`, `Defect`, `DefectResolution`,
`VerificationRecord`.

### C10 · Operations

`Environment`, `Release`, `DeploymentRecord`, `Incident`, `MaintenanceItem`,
`SupportRequest`.

### C11 · AI Orchestration

`WorkflowDefinition` (versioned, repository-sourced), `WorkflowRun`, `WorkflowStep`,
`PromptVersion`, `GenerationRecord` (model, tokens, cost, prompt version),
`EvaluationRun`, `GoldenSetCase`, `EmbeddingRecord`.

**`GenerationRecord` is the cost and lineage source of truth** — aggregated for
per-tenant cost (D-444), and referenced by `ArtifactVersion.lineage`.

### C12 · Integrations

`IntegrationConnection`, `ExternalEntityMapping` (the anticorruption boundary —
our ID ↔ their ID), `SyncState`, `WebhookEvent` (raw, untrusted), `SyncConflict`.

**`ExternalEntityMapping` is where external identity is confined.** No other
table anywhere stores a GitHub issue number or a Jira key — that is the
anticorruption layer expressed in the data model.

### C13 · Analytics

Read models only (`43`). Owns no write-side entity, reads no other context's
tables (D-347).

### C14 · Billing

`Subscription`, `Plan`, `Entitlement`, `UsageRecord` (AI cost, seats, storage),
`Invoice`, `PaymentMethodRef` (token only — no card data, D-87).

### C15 · Platform Administration

`FeatureFlag`, `TenantLifecycleEvent`, `SupportAccessGrant` (JIT, D-76),
`SystemJob`, `PlatformAuditEntry`.

---

## Ownership Matrix

| Data | Owner | Read by others via |
| --- | --- | --- |
| Tenant, User, Membership | Identity (kernel) | Direct — kernel |
| Project | Identity (kernel) | Direct — kernel |
| Artifact, Version, Link, Approval | Delivery Graph (kernel) | Direct — kernel |
| Requirements entities | Requirements | Published Language events + read models |
| Design entities | Design | Events + read models |
| Estimation entities | Estimation | Application service (Commercial) + events |
| Commercial entities | Commercial | Events only — **never direct** (confidentiality) |
| Planning, Execution, Quality entities | Respective context | Events + read models |
| Generation records, prompts, evaluations | AI Orchestration | Application service (cost) + events |
| Integration mappings, sync state | Integrations | Application service |
| Usage, subscriptions, entitlements | Billing | Application service (entitlement checks) |
| Audit entries | Platform Admin / Identity | Query API, tenant-scoped |
| All read models | Analytics | Direct — they are its own |

**Commercial is the strictest row.** Pricing, margin and contract data is
readable only through events that deliberately exclude sensitive fields — the
data-layer expression of P1's ABAC requirement (`02`).

---

## Relationship Patterns

```
        ┌──────────┐
        │  Tenant  │◀────────── every tenant-scoped table (FK)
        └────┬─────┘
             │
        ┌────▼─────┐
        │ Project  │◀────────── most context entities (FK, kernel)
        └────┬─────┘
             │
        ┌────▼──────────┐        ┌─────────────────┐
        │   Artifact    │───────▶│ ArtifactVersion │
        │   (identity)  │  1..n  │   (immutable)   │
        └───────────────┘        └────────┬────────┘
                                          │
                          ┌───────────────┴──────────────┐
                          │                              │
                 ┌────────▼────────┐          ┌──────────▼────────┐
                 │  ArtifactLink   │          │     Approval      │
                 │ version→version │          │  binds to version │
                 └─────────────────┘          └───────────────────┘
                          ▲
                          │ semantic traceability
        ┌─────────────────┴──────────────────┐
        │                                    │
   Requirement ──implements──▶ Task ──verifies──▶ TestCase
   (Requirements)              (Planning)         (Quality)
        │                          │                   │
        └── each also has a plain project_id (structural, no cross-context FK)
```

**Two relationship planes, deliberately separated:**

- **Structural** — `tenant_id`, `project_id`, within-context parent/child. Foreign
  keys, cascades, database-enforced.
- **Semantic** — traceability between artifacts across contexts. Delivery Graph
  links, typed, versioned, queried by traversal.

Conflating them is the mistake that either bloats the graph with plumbing or
buries traceability in foreign keys where it cannot be traversed.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-460 | Every table has exactly one owning context; others access via service or events | A shared database makes cross-boundary `JOIN`s trivially easy, which is how modularity dies |
| D-461 | **No foreign keys across context boundaries**; plain identifiers instead | Cross-context FKs foreclose extraction, invite `JOIN`s, and cascade unpredictably |
| D-462 | Cross-context references validated on write and reconciled daily | Converts a hard guarantee into a detected-and-repaired one |
| D-463 | Foreign keys used freely within a context and to the shared kernel | The kernel is stable and universally depended upon |
| D-464 | Structural references are FKs; semantic traceability is Delivery Graph links | Conflating them bloats the graph or buries traceability where it cannot be traversed |
| D-465 | Shared kernel is exactly eight entities | Its smallness is the entire reason a shared kernel is safe here |
| D-466 | `Project` is in the kernel despite being conceptually context-owned | Nearly every context scopes by it; routing through a boundary would tax every operation |
| D-467 | Commercial data readable only via events that exclude sensitive fields | Data-layer expression of the confidentiality boundary |
| D-468 | External system identifiers confined to `ExternalEntityMapping` | The anticorruption layer expressed in the data model |
| D-469 | `GenerationRecord` is the source of truth for AI cost and lineage | One authoritative place for cost attribution and artifact explicability |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Cross-context `JOIN`s added for a "quick report" | Boundaries silently dissolve; extraction forecloses | Static analysis on repository namespaces; read models provided for exactly this need |
| Dangling cross-context references accumulate | Broken links; confusing UI | Validation on write; daily reconciliation with tracked defects (`41`) |
| Shared kernel grows as contexts want "just one more field" | Becomes a god schema; every change affects everything | ADR required for kernel changes; eight-entity limit is explicit |
| Semantic relationships modelled as FKs | Traceability becomes unqueryable | Reviewed at design time per context; graph links are the only traceability mechanism |
| Ownership matrix drifts from reality | Documented boundaries diverge from code | Schema ownership asserted in tests; new tables declare their owner |

## Dependencies

- **Depends on:** bounded contexts (`03`), domain model (`32`), context map
  (`33`), data and storage (`36`).
- **Depended on by:** read/write models (`43`), data flow (`44`), indexes
  (`45`), GDPR (`48`).

## Future Improvements

- Publish per-context entity diagrams as each context is designed; this
  catalogue is the starting hypothesis.
- Add automated schema-ownership assertion so a table without a declared owner
  fails the build.
- Evaluate schema-per-context if static analysis proves insufficient to prevent
  cross-boundary reads.
