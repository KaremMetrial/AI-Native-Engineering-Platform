# Repository and Folder Strategy

## Purpose

Define how code is organized across repositories and within them, so that the
module boundaries in `03` and the layering in `05` are visible in the file
system and enforceable by tooling. Structure that contradicts architecture
guarantees the architecture erodes.

## Scope

**In scope:** repository topology, monorepo justification, top-level layout,
per-application structure, module internals, naming conventions, and boundary
enforcement.

**Out of scope:** coding style (`13`) and CI pipeline design (`15`).

---

## Repository Strategy: Monorepo

**Decision:** a single repository containing the API, web app, AI orchestration
service, shared packages, infrastructure code, and documentation.

### Why

- **Atomic cross-cutting changes.** Adding a field flows through the API, the
  OpenAPI contract, generated frontend types, and the AI service schema. In one
  repository that is one reviewable, revertible commit. Across four
  repositories it is four PRs, a merge-order dependency, and a window where
  `main` is inconsistent in every one of them.
- **Shared contracts have one home.** API schemas and shared types are
  generated once and consumed everywhere, so drift becomes a build failure
  rather than a production bug.
- **One source of truth for tooling** — linters, formatters, hooks, CI
  configuration. Four repositories drift into four different standards within a
  quarter.
- **Documentation lives with the code it describes**, so it is reviewed in the
  same PR and rots more slowly.
- **Refactoring across boundaries is tractable**, which matters enormously given
  that our module boundaries are explicitly hypotheses (D-29).

### Honest disadvantages

| Disadvantage | Mitigation |
| --- | --- |
| CI runs everything on every change | Affected-target detection: only changed applications and their dependents run. Essential to meet M-4 (< 10 min feedback). |
| Repository grows large over time | Not a practical problem at our scale; shallow clones in CI |
| Access control is repository-wide | Acceptable — one team. Would need reconsidering only with external contractors on part of the codebase. |
| Tooling must be monorepo-aware | Deliberate, one-time investment in the build/task layer |
| Encourages accidental coupling | **The real risk.** Mitigated by dependency rules enforced in CI, not by convention |

**The last row matters most.** A monorepo makes it *easy* to reach across a
boundary — the file is right there. Without mechanical enforcement, a monorepo
plus a modular monolith degrades into a ball of mud faster than polyrepo would.
Boundary enforcement is therefore a prerequisite of this decision, not a
follow-up to it.

### Alternatives considered

| Option | Why not chosen |
| --- | --- |
| **Polyrepo (one per application)** | Forces coordinated releases for cross-cutting changes; contract drift; duplicated tooling; refactoring across boundaries becomes prohibitive. The isolation it offers is one we do not need at one team. |
| **Hybrid (core + separate frontend)** | Splits the two components that share the API contract most tightly — exactly the wrong seam. |
| **Git submodules** | Combines polyrepo's coordination cost with additional operational complexity. Rejected outright. |

**Reversal cost:** low-to-medium. Splitting a monorepo later is mechanical
(history can be preserved with subtree splits). Merging polyrepos is harder.
When reversal costs are asymmetric, start with the one that is cheaper to
undo.

---

## Top-Level Layout

```
/
├── apps/                    Deployable applications
│   ├── api/                 Laravel — Core API, workers, realtime
│   ├── web/                 React SPA
│   └── ai/                  Python — AI orchestration service
│
├── packages/                Shared, versioned internal libraries
│   ├── contracts/           OpenAPI specs + generated types (single source of truth)
│   ├── ui/                  Shared React component library
│   └── config/              Shared tooling configuration
│
├── infra/                   Infrastructure as code
│   ├── terraform/           Environments and modules
│   └── docker/              Container definitions
│
├── docs/                    Engineering documentation (this set)
├── tools/                   Developer and CI scripts
│
├── CLAUDE.md                AI agent entry point
└── README.md
```

**Rationale:** the top level answers "what is this system?" in one screen —
three deployables, shared code, infrastructure, documentation, tooling. A
newcomer orients in seconds, which is a real contributor to M-5.

**`packages/contracts` is the most important directory here.** It holds the API
contract and the types generated from it for both consumers. Making the
contract a first-class artifact — rather than something implied by controller
code — is what makes contract testing possible and keeps the frontend and AI
service honest.

---

## API Application Structure (Laravel)

The structure that expresses `05`'s architecture. Framework-idiomatic layout is
deliberately overridden: default Laravel scaffolding groups by technical type
(all controllers together, all models together), which scatters every bounded
context across the tree and makes boundary enforcement impossible.

```
apps/api/
├── app/
│   ├── Modules/                     ← bounded contexts from doc 03
│   │   ├── Requirements/
│   │   │   ├── Domain/              entities, value objects, domain events,
│   │   │   │                        domain services, repository interfaces
│   │   │   ├── Application/         use cases, handlers, DTOs, outbound ports
│   │   │   ├── Infrastructure/      repository impls, adapters, persistence mapping
│   │   │   └── Presentation/        controllers, requests, resources, policies
│   │   ├── Discovery/
│   │   ├── Design/
│   │   ├── Estimation/
│   │   └── ...
│   │
│   ├── Graph/                       ← Delivery Graph shared kernel
│   ├── Tenancy/                     ← tenant resolution, RLS binding, context
│   └── Shared/                      ← cross-cutting: base types, events, errors
│
├── database/migrations/             chronological, module-prefixed
├── routes/                          per-module route registration
├── tests/
│   ├── Unit/                        mirrors Modules/*/Domain
│   ├── Integration/                 mirrors Modules/*/Infrastructure
│   ├── Feature/                     API-level
│   ├── Architecture/                boundary and layering rules
│   └── Isolation/                   tenant isolation suite (T-1)
└── config/
```

**Organizing by module, then by layer** — rather than the reverse — means
everything about Requirements is in one place. This is what makes eventual
extraction (`05`'s seams) a directory move rather than an archaeology project.

**`tests/Isolation` is a top-level test category** because T-1 is the only
requirement whose failure is unrecoverable. Giving it its own category makes it
visible, separately runnable, and impossible to quietly skip.

### Module internal rules

| Rule | Enforcement |
| --- | --- |
| Domain imports nothing from Application, Infrastructure or Presentation | Static dependency analysis |
| Domain imports no framework code | Static analysis on namespace prefixes |
| Infrastructure and Presentation never import each other | Static analysis |
| A module imports another module only via its `Application` layer | Static analysis |
| Only `Tenancy` and `Shared` are importable by all | Static analysis |
| `Graph` is importable by all; `Graph` imports no module | Static analysis |

Every rule is mechanically checked and fails the build (M-2). **A boundary rule
that is only documented is a boundary rule that is already broken** — this is
the enforcement that D-29 depends on.

---

## Web Application Structure

```
apps/web/src/
├── features/                ← mirrors backend bounded contexts
│   ├── requirements/        components, hooks, api, types, routes
│   ├── discovery/
│   └── ...
├── shared/                  ui primitives, hooks, utilities, api client
├── app/                     routing, providers, layout, error boundaries
└── lib/                     framework-adjacent integrations
```

**Feature-based, mirroring backend contexts.** A change to requirements
functionality touches `features/requirements` on both sides — the mental model
stays consistent across the stack, and a full-stack change is easy to review.

Rules: features do not import from each other (shared code moves to `shared/`);
`shared/` never imports from `features/`. Enforced by lint rules.

---

## AI Service Structure

```
apps/ai/src/
├── workflows/               one module per capability from doc 10
├── prompts/                 versioned prompt artifacts (D-93)
├── retrieval/               graph + vector retrieval, tenant scoping
├── providers/               model provider abstraction and routing
├── guardrails/              injection defence, validation, limits
├── evaluation/              golden sets, judges, harness
└── api/                     FastAPI routes and schemas
```

**`prompts/` is versioned source**, reviewed like code (D-93). **`evaluation/`
sits alongside it as a first-class directory**, not inside tests — evaluation is
a product capability that gates releases (D-96), not merely a test suite.

---

## Naming Conventions

| Element | Convention | Example |
| --- | --- | --- |
| Modules / contexts | PascalCase, singular domain noun | `Requirements`, `Estimation` |
| PHP classes | PascalCase; suffix by role | `GenerateBrdHandler`, `RequirementRepository` |
| Domain events | Past tense | `RequirementApproved` |
| Commands / use cases | Imperative | `ApproveRequirement` |
| Database tables | snake_case plural | `requirement_versions` |
| API routes | kebab-case plural nouns | `/api/v1/requirement-versions` |
| React components | PascalCase | `RequirementEditor.tsx` |
| React hooks | `use` prefix | `useRequirementVersions` |
| Python modules | snake_case | `brd_synthesis.py` |
| Feature flags | `context.feature` | `requirements.ai_review` |

Naming is enforced by linting where possible. The purpose is not aesthetic
uniformity: consistent naming means a developer can *predict* where something
lives and what it is called, which compounds across thousands of interactions.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-102 | Single monorepo for all applications and infrastructure | Atomic cross-cutting changes; one contract source; tractable refactoring |
| D-103 | Boundary enforcement is a precondition of the monorepo | A monorepo without it degrades faster than polyrepo |
| D-104 | CI uses affected-target detection | Required to meet M-4 as the repository grows |
| D-105 | API organized by module first, layer second | Keeps a context in one place; makes extraction a directory move |
| D-106 | Framework-idiomatic layout deliberately overridden | Type-based grouping scatters contexts and defeats boundary enforcement |
| D-107 | Tenant isolation tests are a top-level test category | Makes the unrecoverable failure class visible and impossible to skip quietly |
| D-108 | Frontend features mirror backend contexts | Consistent mental model across the stack |
| D-109 | Prompts and evaluation are first-class source directories | Prompts are behaviour; evaluation gates releases |
| D-110 | API contract is a shared package, not implied by controllers | Enables contract testing and prevents drift |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| CI time grows until feedback is too slow | M-4 breached; developers batch changes and quality drops | Affected-target detection from day one; pipeline duration tracked as a metric |
| Monorepo proximity encourages boundary violations | Modular monolith erodes | Mechanical enforcement; violations block merge |
| Deep directory nesting harms navigation | Slower onboarding | Structure is uniform, so it is learned once and applies everywhere |
| Shared packages become a dumping ground | Coupling through `shared/` | Explicit ownership; anything not genuinely shared moves back to its feature |
| Structure enforced but architecture ignored | Correct folders, wrong dependencies | Architecture tests check dependencies, not just file locations |

## Dependencies

- **Depends on:** module boundaries (`03`), architecture (`05`), technology (`06`).
- **Depended on by:** coding standards, testing strategy, deployment strategy.

## Future Improvements

- Add a module generator so new modules start with correct structure and
  architecture tests, making the right way the fastest way.
- Introduce CODEOWNERS per module as the team grows past a single squad.
- Re-evaluate the monorepo if external contributors ever need partial access.
