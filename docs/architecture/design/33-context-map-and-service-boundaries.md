# Context Map and Service Boundaries

## Purpose

Define the relationships between bounded contexts — who depends on whom, on what
terms, and how models are translated at each boundary. `03` names the contexts;
this document defines the contracts between them and where a context boundary
becomes a service boundary.

A context map is the artifact that prevents the most common failure in modular
systems: boundaries that exist on paper while models leak across them freely.

## Scope

**In scope:** integration patterns between contexts, the context map, translation
and anticorruption, upstream/downstream power relationships, and the criteria
distinguishing a module boundary from a service boundary.

**Out of scope:** the contexts themselves (`03`) and their internal models
(`32`).

---

## Integration Patterns

The vocabulary used in the map. Each describes a different power relationship
and a different obligation.

| Pattern | Meaning | Cost |
| --- | --- | --- |
| **Shared Kernel** | Two contexts share a model, jointly owned | Highest coupling — any change requires agreement from all sharers |
| **Customer–Supplier** | Downstream has a say in upstream's roadmap | Coordination overhead, negotiated priorities |
| **Conformist** | Downstream accepts upstream's model as-is | Zero translation cost, zero influence, upstream churn propagates |
| **Anticorruption Layer** | Downstream translates upstream's model into its own | Translation code to build and maintain; full insulation |
| **Open Host Service** | Upstream publishes a general interface for many consumers | Interface must be stable and general; consumers cost nothing to add |
| **Published Language** | A shared, well-documented interchange format | Format governance; decouples both sides from each other's internals |
| **Separate Ways** | No integration at all | None — and duplication where needs overlap |

**Choosing a pattern is choosing what you are willing to pay.** A Conformist
relationship is free until upstream changes; an Anticorruption Layer costs
continuously and insulates permanently. The map below states which cost we
accepted where, and why.

---

## The Context Map

```
                    ┌───────────────────────────────────────┐
                    │      IDENTITY & TENANCY (C1)          │
                    │        Shared Kernel (minimal)        │
                    │   tenant id · actor id · membership   │
                    └───────────────┬───────────────────────┘
                                    │ every context conforms
        ┌───────────────────────────┼───────────────────────────┐
        │                           │                           │
        │              ┌────────────▼─────────────┐             │
        │              │  DELIVERY GRAPH (kernel) │             │
        │              │    Shared Kernel         │             │
        │              │  artifacts · versions    │             │
        │              │  links · lineage         │             │
        │              └────────────┬─────────────┘             │
        │                           │                           │
   ┌────▼─────┐  cust/supp   ┌──────▼──────┐  pub.lang   ┌──────▼──────┐
   │DISCOVERY │─────────────▶│REQUIREMENTS │────────────▶│   DESIGN    │
   │   (C2)   │              │    (C3)     │             │    (C4)     │
   └──────────┘              └──────┬──────┘             └──────┬──────┘
                                    │ pub. lang                 │
                       ┌────────────┼───────────┐               │
                       │            │           │               │
                ┌──────▼─────┐ ┌────▼─────┐ ┌───▼──────┐        │
                │ ESTIMATION │ │ PLANNING │ │ QUALITY  │◀───────┘
                │    (C5)    │ │   (C7)   │ │   (C9)   │
                └──────┬─────┘ └────┬─────┘ └───┬──────┘
                       │ cust/supp  │           │
                ┌──────▼─────┐      │           │
                │ COMMERCIAL │      │           │
                │    (C6)    │      │           │
                └────────────┘      │           │
                              ┌─────▼─────┐     │
                              │ EXECUTION │◀────┘
                              │   (C8)    │
                              └─────┬─────┘
                                    │
                              ┌─────▼──────┐
                              │ OPERATIONS │
                              │   (C10)    │
                              └────────────┘

   ┌──────────────────┐   ┌──────────────────┐   ┌──────────────────┐
   │ AI ORCHESTRATION │   │   INTEGRATIONS   │   │    ANALYTICS     │
   │      (C11)       │   │      (C12)       │   │      (C13)       │
   │  Open Host Svc   │   │ Anticorruption   │   │   Conformist     │
   │  serves all      │   │ wraps externals  │   │   reads events   │
   └──────────────────┘   └──────────────────┘   └──────────────────┘
```

---

## Relationships in Detail

### Identity & Tenancy → all contexts · Shared Kernel (minimal)

**Decision.** Tenant identity, actor identity and membership form a deliberately
tiny shared kernel that every context depends on directly.

**Reasoning.** Tenant scoping is required by every query in the system (`07`).
Routing it through an interface or translating it per context would add
indirection to the single most-used concept in the platform, and would create
opportunities for a context to get scoping wrong. The kernel is safe *because*
it is tiny and stable: identifiers and membership, no business rules.

**Alternatives.** *Identity as an upstream service each context conforms to* —
cleaner separation, and adds a call or translation to literally every operation.
*Each context holds its own user model* — full independence, and now user
identity is duplicated fifteen times and can diverge.

**Trade-offs.** A shared kernel is the highest-coupling pattern available. A
change to it affects every context, so changes require broad agreement and are
architecturally significant (ADR required).

**Benefits.** Tenant scoping is uniform and cannot be reinterpreted per context.

**Long-term impact.** The stability of this kernel is a precondition for
everything else; instability here would propagate to fifteen contexts at once.
Its smallness is what makes it stable.

### Delivery Graph → artifact-producing contexts · Shared Kernel

Same pattern, same reasoning: traceability is inherently cross-context, and
distributing it would mean each context reimplements linking with no ability to
answer a global question. Constrained identically — identity, versions, links,
lineage, and **no business rules** (D-13).

**The rule that keeps it safe:** business meaning about an artifact lives in the
owning context. The graph knows a link exists; it does not know what a
requirement *is*.

### Discovery → Requirements · Customer–Supplier

Requirements consumes Discovery's output and has legitimate influence over its
shape — if discovery data lacks what requirement synthesis needs, that is
Discovery's problem to solve.

**Why not Conformist:** these two contexts evolve together in early phases, and
Requirements' needs are the primary driver of what Discovery should capture.
Denying it influence would produce discovery output optimized for nothing.

### Requirements → Design, Estimation, Planning, Quality · Published Language

**Decision.** Requirements publishes approved artifacts as a documented,
versioned interchange format. Consumers subscribe; Requirements does not know
who they are.

**Reasoning.** Requirements has four consumers with different needs. Modelling
each as Customer–Supplier would give four contexts influence over one, producing
a model pulled in four directions. A Published Language — a stable, documented
artifact format — lets each consumer take what it needs without negotiating.

This is also what makes the dependency direction acyclic (D-17): Requirements
emits `RequirementApproved` and does not know Planning exists.

**Alternatives.** *Direct calls from each consumer* — couples Requirements to
four consumers and creates cycles. *Conformist on Requirements' internal model* —
exposes internals, so any refactor breaks four contexts.

**Trade-offs.** The published format must be designed and versioned as a real
contract (`27`), and it will sometimes lag what a consumer wants. Format changes
are additive-only.

**Benefits.** Requirements refactors freely behind its published format.
Consumers are added without touching it.

### Estimation → Commercial · Customer–Supplier

Commercial depends on estimates and has legitimate influence — a proposal needs
confidence ranges and driver breakdowns that only Estimation can provide, so
Commercial's needs shape Estimation's output.

### Integrations → external systems · Anticorruption Layer

**Decision.** Every external system is wrapped in a translation layer. External
models never enter the domain.

**Reasoning.** Third-party models are shaped by their vendors' concerns, not
ours, and they change on the vendor's schedule. A GitHub pull request, a Jira
issue and a Linear issue are three different models of overlapping concepts; if
any of them reaches the domain, we have adopted a foreign model we do not
control and cannot version.

**Alternatives.** *Conformist* — cheapest, and it means a vendor's API change
propagates into the domain model, and supporting a second vendor requires
modelling both. *Separate contexts per provider* — full isolation, and duplicates
sync logic per provider.

**Trade-offs.** Translation code per integration, maintained forever, and a
translation layer that can lose information the domain would have wanted.

**Benefits.** Adding a second Git provider is a new adapter, not a domain change.
A vendor's breaking change is contained in one translator.

**Long-term impact.** This is the boundary most certain to be tested — every
external API we integrate will change. Insulation is the difference between a
contained adapter change and a domain migration.

### AI Orchestration → all contexts · Open Host Service

**Decision.** The AI service publishes a general workflow interface. Contexts
describe *what* they need generated; the service owns *how*.

**Reasoning.** Every context needs AI. Bilateral relationships with fifteen
contexts would be unmanageable and would push AI concerns into each of them. A
generic host interface — invoke workflow, receive schema-conformant result plus
lineage — serves all of them without the service knowing any context's internals.

**Trade-offs.** The interface must be general enough for every context and
stable, which means it cannot expose workflow-specific conveniences. Generality
occasionally makes a specific use awkward.

**Benefits.** The AI service evolves independently — the explicit design goal
(D-296). Contexts remain free of prompt and model concerns.

### Analytics ← all contexts · Conformist

**Decision.** Analytics consumes published events as-is, translating nothing, and
has no influence over producers.

**Reasoning.** Analytics reads from every context. Giving it influence would mean
fifteen contexts accommodating reporting needs — the standard path by which
reporting requirements distort a domain model. Conformist is the correct
asymmetry: Analytics absorbs the cost of adapting.

**Trade-offs.** Producer changes can break projections, so Analytics carries the
maintenance burden. Accepted deliberately — it is the right place for it.

**Critical constraint:** Analytics reads **only** published events and read
models, never other contexts' tables (D-16). This is the rule that stops
analytics becoming the coupling that silently defeats modularity — the most
common way a modular system degrades.

---

## Relationship Summary

| Upstream | Downstream | Pattern | Cost accepted |
| --- | --- | --- | --- |
| Identity & Tenancy | All | Shared Kernel (minimal) | High coupling, bounded by smallness |
| Delivery Graph | Artifact producers | Shared Kernel (minimal) | Same |
| Discovery | Requirements | Customer–Supplier | Coordination |
| Requirements | Design, Estimation, Planning, Quality | Published Language | Contract governance |
| Design | Planning, Quality | Published Language | Contract governance |
| Estimation | Commercial, Planning | Customer–Supplier | Coordination |
| Planning | Execution | Published Language | Contract governance |
| Execution | Quality, Operations | Published Language | Contract governance |
| External systems | Integrations | Anticorruption Layer | Translation maintenance |
| AI Orchestration | All | Open Host Service | Interface generality |
| All | Analytics | Conformist | Downstream absorbs churn |
| Billing | All | Open Host Service (entitlements) | Interface stability |

---

## Module Boundary vs Service Boundary

Every context above is a **module**. Only one is currently a **service**.

**Decision.** A context becomes a separate service only when it satisfies a
trigger with evidence; otherwise it remains an in-process module with enforced
boundaries.

**Reasoning.** A context boundary and a deployment boundary are different
decisions that are routinely conflated. Context boundaries are about *model
ownership*; service boundaries are about *operational independence*. Making every
context a service pays distributed-systems costs — network failure, partial
failure, distributed transactions, versioned wire contracts, independent
deployment pipelines — to solve problems most contexts do not have.

**Extraction triggers** (any one, demonstrated with evidence, requiring an ADR
per D-39):

| Trigger | Rationale |
| --- | --- |
| **Independent scaling profile** | Its load is uncorrelated with the rest and materially different in shape |
| **Independent failure isolation required** | Its failure must not affect the core (A-5) |
| **Different runtime genuinely required** | The ecosystem it needs does not exist in ours |
| **Independent deploy cadence required** | It changes far more often, and coupling deploys is a real constraint |
| **Independent team ownership at scale** | Deploy coordination has become a measured bottleneck |

**AI Orchestration is extracted** because it satisfies four of the five (`05`,
D-30). No other context currently satisfies any.

**Pre-identified candidates**, with the trigger each would need to meet:

| Candidate | Trigger it would need |
| --- | --- |
| Analytics | Read load measurably interferes with transactional performance |
| Integrations | Third-party sync volume or failure isolation demands it |
| Estimation | Compute intensity justifies independent scaling |
| Billing | A compliance boundary or vendor requirement forces isolation |

**"It feels too big" is not a trigger** (D-39). Nor is team preference, nor a
desire to use a different technology — that is `23`'s anti-heuristic and the most
seductive wrong reason.

**Why extraction is cheap when it comes:** because modules already own their data,
communicate only through published interfaces and events, and never share tables,
extraction replaces in-process calls with network calls and splits the schema
along an existing seam. The boundary work is already done — which is the entire
argument for the modular monolith (D-29).

---

## Boundary Enforcement

The map is only real if it is enforced. Documented relationships that are not
checked become fiction within one release cycle.

| Rule | Enforcement |
| --- | --- |
| A module imports another only via its Application layer | Static dependency analysis, blocking |
| No module imports another's Domain or Infrastructure | Static dependency analysis, blocking |
| No module reads another's tables | Schema ownership check; repository namespacing |
| Only Tenancy and Graph are importable by all | Namespace allowlist |
| Analytics imports no module | Dependency analysis |
| External SDKs appear only in Integrations adapters | Import restriction |
| Provider SDKs appear only in the AI service's Provider Router | Import restriction (D-323) |

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-342 | Each context relationship is assigned an explicit integration pattern | Choosing a pattern is choosing which cost you accept; unstated patterns default to accidental coupling |
| D-343 | Identity/Tenancy and Delivery Graph are minimal shared kernels | Universally needed; safe only because they are tiny and hold no business rules |
| D-344 | Requirements publishes a Published Language, not per-consumer interfaces | Four Customer–Supplier relationships would pull one model in four directions |
| D-345 | All external systems wrapped in anticorruption layers | Every external API will change; insulation contains it to one translator |
| D-346 | AI Orchestration is an Open Host Service | Bilateral relationships with fifteen contexts are unmanageable |
| D-347 | Analytics is Conformist and reads only events and read models | Prevents reporting needs distorting fifteen domain models |
| D-348 | Context boundary and service boundary are separate decisions | Conflating them pays distributed-systems costs to solve problems most contexts do not have |
| D-349 | Service extraction requires a demonstrated trigger and an ADR | "It feels too big" and technology preference are not triggers |
| D-350 | Every boundary rule is mechanically enforced | An unenforced context map is fiction within one release |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Shared kernels accumulate business rules | Become god modules; every change touches everything | ADR required for any kernel change; "no business rules" is an explicit rule |
| Published Language changes break consumers | Cross-context breakage | Additive-only evolution (`27`); contract tests |
| Analytics reaches into module tables for convenience | Modularity silently defeated | Dependency analysis blocks it; schema ownership enforced |
| Anticorruption layers skipped for a "simple" integration | External model leaks into the domain | Import restrictions confine external SDKs to adapters |
| Premature extraction on preference rather than evidence | Distributed complexity with no benefit | Triggers required and evidenced in an ADR |
| Context map documented but never updated | Diverges from reality; misleads newcomers | Reviewed at phase boundaries; enforcement rules keep code honest even if the diagram lags |

## Dependencies

- **Depends on:** bounded contexts (`03`), architecture (`05`), domain model
  (`32`), architecture philosophy (`23`).
- **Depended on by:** communication (`34`), events (`35`), data architecture
  (`36`).

## Future Improvements

- Publish the Published Language schemas for Requirements and Design before
  their first consumer is built.
- Add a context map diagram per phase, showing only the contexts that exist
  then — the full map is aspirational until Phase 4.
- Record extraction trigger measurements as a standing report, so extraction
  decisions are evidence-driven rather than argued.
