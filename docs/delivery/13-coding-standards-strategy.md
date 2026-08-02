# Coding Standards Strategy

## Purpose

Define the standards that keep the codebase consistent, safe and maintainable,
and — more importantly — define how they are **enforced**. The charter forbids
ignoring static analysis and compiler warnings; this document makes that
mechanical rather than aspirational.

## Scope

**In scope:** enforcement philosophy, language standards, architectural
enforcement, error handling, naming and comment policy, security-sensitive
patterns, and standards for AI-assisted contribution.

**Out of scope:** file organization (`11`), review process (`16`).

---

## Enforcement Philosophy

**A standard that is not automatically enforced is a suggestion, and
suggestions decay.**

Three tiers, and every rule belongs to exactly one:

| Tier | Mechanism | Examples |
| --- | --- | --- |
| **Automated, blocking** | Formatter, linter, static analyzer, architecture test — fails the build | Formatting, types, complexity, module boundaries, security patterns |
| **Automated, advisory** | Reported, does not block | Coverage trends, bundle size warnings, duplication metrics |
| **Human judgment** | Code review | Naming quality, abstraction fit, whether the design is right |

**Deliberate consequence: reviewers never comment on formatting, import order,
or anything a tool can decide.** Those comments are noise that crowds out the
judgment only a human can supply. If a debate about style recurs, the answer is
to encode it in a tool, not to relitigate it in each PR.

**Corollary: standards are ratcheted, never relaxed.** A rule enabled is a rule
that stays enabled. Suppressions require an inline justification and are
reviewed; a growing suppression count is treated as a defect trend.

---

## PHP / Laravel Standards

### Language

- **`declare(strict_types=1)` in every file.** Without it, PHP silently coerces
  types at boundaries and type declarations become decorative.
- **PSR-12 formatting**, applied automatically. Never discussed in review.
- **Static analysis at maximum strictness**, blocking. Starting at maximum is
  essential — retrofitting strictness onto an existing codebase is a project;
  starting there is free.
- **Full type declarations** on parameters, returns and properties. Generics
  expressed through analyzer annotations where the language cannot.
- **`readonly` properties and constructor promotion** for value objects.
- **Enums** instead of class constants or magic strings for closed sets.
- **`final` by default** on classes. Inheritance is opened deliberately, not by
  accident — this operationalizes the charter's composition-over-inheritance
  principle.

### Domain layer

The strictest rules, because this is where the value is:

- No framework imports. No `Illuminate\*` in `Domain/`, enforced by static
  analysis (`11`).
- No IO — no database, HTTP, filesystem or clock access. Time is injected.
- Entities protect their invariants; no public setters that permit an invalid
  state.
- Value objects are immutable and self-validating.
- Domain exceptions are domain concepts, not framework exceptions.

**Why this severity:** these rules are what make the domain testable without
infrastructure, which is what makes M-3 (85% domain coverage) achievable in
practice rather than in theory. They are also what protects the valuable logic
from framework churn.

### Application layer

- One use case per class, one public entry method.
- Transaction boundaries live here — never in the domain or presentation layers.
- Outbound dependencies are declared as interfaces (ports) owned by this layer,
  implemented in infrastructure. This is the dependency inversion that keeps the
  dependency rule intact.

### Persistence

- **No raw SQL string concatenation**, ever. Parameterized queries only,
  enforced by static analysis.
- **Queries are tenant-scoped by construction** — the base query builder applies
  tenant scope, so forgetting it is not an available option (D-83).
- N+1 detection enabled in development and test; a detected N+1 fails the test
  suite rather than becoming a production performance incident.
- Migrations follow expand-contract (D-120).

### Explicitly forbidden

| Pattern | Reason |
| --- | --- |
| Facades in domain and application layers | Hidden static coupling; defeats dependency injection and testability |
| Service location / container resolution outside composition root | Dependencies must be visible in the constructor |
| Global helpers touching request or auth state in domain code | Hidden dependency on request context; breaks worker execution |
| Fat models with business logic in Eloquent entities | Couples domain rules to the ORM; makes them untestable in isolation |
| `env()` outside configuration files | Returns null when configuration is cached — a classic production-only failure |
| Suppressing analyzer errors without written justification | Defeats the entire purpose of static analysis |

---

## TypeScript / React Standards

- **Strict mode fully enabled.** `any` is prohibited; `unknown` with narrowing
  is the escape hatch. An `any` at an API boundary silently discards the type
  safety that justified choosing TypeScript.
- **API types are generated** from the OpenAPI contract (`packages/contracts`),
  never hand-written. Hand-written types drift, and drifted types are worse than
  no types because they are trusted.
- Function components with hooks; no class components.
- Server state and client state kept distinct — server state through the data
  layer with proper caching and invalidation, never duplicated into local state.
- Components are presentational by default; data fetching is confined to
  container components and hooks.
- Accessibility lint rules blocking (U-1/U-2): semantic elements, labelled
  controls, keyboard handlers. **Accessibility is cheap to build in and
  expensive to retrofit**, which is why it is enforced from the first component
  rather than at Phase 2 when it becomes binding.
- Error boundaries at route level; no unhandled promise rejections.
- Bundle size budgets in CI, blocking on regression (P-4).

---

## Python Standards

- **Type hints on all public functions**, checked by a static type checker in
  strict mode.
- **Pydantic models for every boundary** — API requests/responses and model
  structured outputs. This is both validation and schema definition, so one
  artifact serves both purposes (D-52).
- Async-first for IO; blocking calls in async paths are a defect, since one
  blocking call stalls the event loop and defeats the concurrency model that
  justified FastAPI.
- **Prompts are versioned artifacts, never inline string literals** (D-93).
- Every model interaction goes through the provider abstraction — never a direct
  SDK call (D-51). Enforced by an import restriction.

---

## Architectural Enforcement

Rules from `05` and `11`, mechanically verified. Any violation fails the build.

| Rule | Check |
| --- | --- |
| Domain depends on nothing outward | Dependency analysis |
| Layer dependency direction | Dependency analysis |
| Cross-module access only via Application layer | Dependency analysis |
| No framework code in Domain | Namespace restriction |
| Every HTTP endpoint has an authorization policy (SEC-4) | Architecture test |
| Every tenant-scoped table has `tenant_id` and an RLS policy | Schema test |
| No AI inference in a synchronous request path (P-9) | Architecture test |
| No direct provider SDK imports outside the abstraction | Import restriction |
| Frontend features do not import each other | Lint rule |

**Two of these are worth calling out.** The endpoint-policy check (SEC-4) means
an engineer cannot ship an unprotected endpoint even by forgetting — the build
fails. The P-9 check enforces the single most important performance constraint
in the architecture, which would otherwise be violated accidentally and
discovered under load.

---

## Error Handling

- **Fail fast.** Invalid state raises immediately; never silently continue with
  a partial or defaulted value.
- **No empty catch blocks** and no catching a broad exception type without
  rethrowing or handling meaningfully — enforced by static analysis.
- Domain errors are typed domain concepts, mapped to transport errors at the
  edge.
- **Internal detail never reaches clients** — no stack traces, no SQL, no
  internal identifiers in API responses.
- Every error carries a correlation ID linking the user-facing message to the
  logs (O-2).
- Retries are bounded, use exponential backoff with jitter, and are applied only
  to genuinely transient failures. Retrying a validation error is a bug that
  amplifies load.

---

## Naming and Comments

**Naming** follows `11`'s conventions. Beyond mechanics: names state intent, not
type or implementation. `$activeRequirements` beats `$reqArray`. Domain
terminology is used consistently and matches the language the business uses —
diverging vocabulary between code and domain experts is a persistent source of
misunderstanding.

**Comment policy:**

- Comments explain **why**, never **what**. A comment restating the code is
  noise that goes stale.
- Non-obvious business rules get a comment with the reason and, where useful, a
  link to the requirement.
- Public interfaces are documented at the contract level: purpose, invariants,
  failure modes.
- **No TODOs, no commented-out code, no placeholders** — charter absolutes,
  enforced by linting. Deferred work goes in the tracker, where it is visible;
  a TODO in code is invisible to planning and invisible to the person who needs
  to know about it.

---

## Security-Sensitive Patterns

Enforced automatically because these are the failures that matter most:

| Pattern | Enforcement |
| --- | --- |
| No secrets in source | Secret scanning, blocking (SEC-3) |
| No string-concatenated SQL | Static analysis |
| No unescaped HTML rendering without justification | Lint rule |
| No user input in shell execution | Static analysis |
| No disabled TLS verification | Static analysis |
| Tenant scoping on all data access | Base query builder + isolation tests |
| Authorization on all endpoints | Architecture test |
| No sensitive fields in logs | Redacting logger (D-79) |

---

## Standards for AI-Assisted Contribution

A meaningful share of code in this repository will be AI-assisted. This changes
what standards must do — and it is why the enforcement philosophy above is
strict rather than merely thorough.

- **Every rule above applies identically** regardless of who or what wrote the
  code. Authorship is not a quality argument.
- **The human who submits the PR owns it entirely** — its correctness, its
  security, and its fit with the architecture. "The model wrote it" is not a
  defence, and is not accepted in review.
- **Machine-checkable standards matter more, not less.** AI-assisted code is
  produced faster than humans can carefully review it. Automated gates are what
  scale review capacity; without them, volume simply outpaces scrutiny.
- **Generated code is reviewed for architectural fit specifically.** Models
  reproduce common patterns, and common patterns are often framework-idiomatic
  ones that violate our deliberate layering (`11`, D-106). This is the most
  frequent failure mode, and reviewers are told to look for it.
- **`CLAUDE.md` carries the charter into every AI session**, so agents work to
  these standards by default rather than to generic ones.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-125 | Every rule is automated-blocking, automated-advisory, or human judgment | An unenforced standard decays |
| D-126 | Reviewers never comment on tool-decidable issues | Preserves review attention for judgment |
| D-127 | Static analysis at maximum strictness from the first commit | Retrofitting strictness is a project; starting there is free |
| D-128 | Standards ratchet up, never down; suppressions require justification | Prevents slow erosion |
| D-129 | `strict_types` everywhere; `final` by default | Type declarations must be real; inheritance opened deliberately |
| D-130 | Domain layer forbids framework imports and all IO | Makes M-3 achievable and insulates domain from framework churn |
| D-131 | Tenant scoping applied by the base query builder | Makes the safe path the default path (D-83) |
| D-132 | `any` prohibited in TypeScript; API types generated from the contract | Hand-written types drift and are then trusted while wrong |
| D-133 | Accessibility lint rules blocking from the first component | Cheap to build in, expensive to retrofit |
| D-134 | N+1 detection fails tests | Converts a production performance incident into a test failure |
| D-135 | Comments explain why, never what; no TODOs in code | Deferred work belongs where planning can see it |
| D-136 | Standards apply identically to AI-generated code; the submitter owns it | Authorship is not a quality argument |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Strict analysis slows early velocity | Perceived drag; pressure to relax | Real cost, accepted deliberately — retrofitting is far more expensive |
| Suppression comments proliferate | Static analysis silently hollowed out | Suppression count tracked as a defect trend; justifications reviewed |
| Architecture tests are hard to write and get skipped | Boundaries erode | Written alongside the first module, so later modules inherit them |
| Rules feel arbitrary to new engineers | Resentment and workarounds | Every rule here carries its reason; the reason is the standard |
| AI-generated code passes gates but is architecturally wrong | Subtle erosion at volume | Review focuses explicitly on architectural fit, which tools cannot check |
| Enforcement tooling itself becomes slow | Breaches M-4 | Tool runtime tracked; incremental analysis where supported |

## Dependencies

- **Depends on:** architecture (`05`), technology (`06`), tenancy (`07`),
  security (`09`), repository strategy (`11`).
- **Depended on by:** review process, quality gates, definition of done.

## Future Improvements

- Publish the concrete tool configuration alongside the first module, so rules
  are executable rather than described.
- Add automated complexity and duplication reporting once a baseline exists.
- Add a custom analyzer rule for tenant scoping if the base query builder proves
  insufficient in practice.
