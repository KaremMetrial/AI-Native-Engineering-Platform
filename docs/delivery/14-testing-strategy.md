# Testing Strategy

## Purpose

Define what we test, at which level, and why — so that the suite gives genuine
confidence to deploy rather than a coverage number. The charter forbids code
that cannot be tested; this document defines what testing that code means.

## Scope

**In scope:** test levels and their contracts, AI evaluation testing, tenant
isolation testing, test data, performance and security testing, and the
practices that keep the suite trustworthy.

**Out of scope:** CI pipeline mechanics (`15`) and quality gate thresholds
(`16`).

---

## Testing Philosophy

**Tests exist to enable confident change.** A test that does not increase
confidence, or that breaks when behaviour is unchanged, is a liability — it
costs maintenance and trains the team to ignore failures.

Three principles follow:

1. **Test behaviour, not implementation.** Tests coupled to internal structure
   break on every refactor. Since our module boundaries are explicitly
   hypotheses (D-29), a suite that resists refactoring would prevent the
   correction the architecture depends on.
2. **The test pyramid is a cost model, not dogma.** Fast tests are cheap to run
   and precise about failure; slow tests are expensive and vague. Weight the
   suite accordingly — but the correct shape depends on where the risk is, and
   for this platform some risk lives in places unit tests cannot reach.
3. **A flaky test is worse than no test.** It trains the team to re-run rather
   than investigate, and eventually to ignore red builds entirely. Flaky tests
   are quarantined immediately and fixed or deleted — never tolerated.

---

## Test Levels

### Unit tests — the foundation

**Target: the domain layer.** This is where the business rules live —
estimation logic, traceability rules, versioning and approval semantics,
invariants — and it is where a defect is both most likely and most damaging.

- No database, no HTTP, no filesystem, no clock. The domain rules in `13` make
  this possible by construction.
- Milliseconds each; the whole domain suite runs in seconds.
- **Coverage target > 85% on domain paths (M-3)** — not a global number,
  because global coverage targets get satisfied by testing getters while the
  logic that matters goes untested.
- Failure points precisely at the broken rule.

**Why the domain layer is worth this investment:** it is the only code that is
genuinely ours. Framework glue is well-tested by its maintainers; controllers
are thin; repositories are mostly mapping. The domain is where our thinking
lives, and thinking is what has bugs.

### Integration tests — the seams

Test components against real infrastructure, because the bugs here are precisely
the ones mocks hide.

- **Real PostgreSQL in a container** — never an in-memory substitute. SQLite
  does not implement RLS, behaves differently on constraints and JSON, and would
  make our most important security control (D-54) untestable. **Testing against
  a different database than production is testing a different system.**
- Real Redis for cache, lock and rate-limit behaviour.
- Repository implementations, migrations, RLS policies, queue handlers,
  transaction boundaries.
- External HTTP dependencies stubbed at the boundary with contract-verified
  fixtures.

### Feature / API tests — the contract

Exercise the API as a client does: full request cycle, authentication,
authorization, validation, response shape.

- **Every endpoint has an authorization test proving unauthorized access is
  denied** — not merely that authorized access works. The negative case is the
  one that matters, and it is the one most often omitted.
- Error responses tested as deliberately as success responses; error handling is
  where implementations are weakest.
- Idempotency verified on mutating endpoints (D-37).

### Contract tests — cross-application consistency

The API contract in `packages/contracts` (D-110) is verified from both sides:
the API's responses conform to it, and generated frontend/AI-service types are
derived from it. A breaking change becomes a build failure rather than a runtime
surprise in a different application.

### End-to-end tests — critical paths only

Browser-driven, against a fully deployed environment.

**Deliberately few — under 30 scenarios.** E2E tests are slow, the most flaky,
and the most expensive to maintain. They are reserved for journeys where failure
is unacceptable and no lower level can provide the confidence:

- Sign-up, tenant provisioning, first project
- Discovery → BRD generation → approval
- Proposal generation → client view → approval
- Authentication, MFA, session handling
- Billing and subscription changes

**Anti-pattern explicitly rejected:** a large E2E suite as a substitute for
lower-level testing. It produces slow feedback (breaching M-4), flaky results,
and failures that indicate *something* broke without indicating what.

---

## Tenant Isolation Testing

**A dedicated, top-level test category** (D-107), because T-1 and T-7 are the
only requirements whose failure is unrecoverable.

| Test | Verifies |
| --- | --- |
| Cross-tenant read denial | Tenant A cannot read Tenant B's artifacts by any route, including direct ID access |
| Cross-tenant write denial | Tenant A cannot modify Tenant B's data |
| **RLS actually active** | A deliberately unscoped query returns zero rows — proves policies are enforced, not merely defined |
| **Database role privileges** | Application role lacks `BYPASSRLS` and does not own tables (D-55) |
| Connection pool isolation | Concurrent multi-tenant requests never leak session tenant state |
| Async context propagation | Jobs and events carry tenant context; missing context fails closed (D-57) |
| Cache key isolation | Cache keys are tenant-partitioned (D-58) |
| Retrieval isolation (T-7) | Vector and search retrieval cannot return another tenant's content |
| External stakeholder scope | P8 grants expose only explicitly shared resources, nothing adjacent |

**The RLS-active and role-privilege tests are the subtle ones.** Every other
isolation test can pass while RLS is completely inert — because application-level
scoping is also filtering correctly. If someone later grants the application
role table ownership or `BYPASSRLS`, the entire database-level defence
disappears silently, with a green suite. These two tests are what make layer 4
of the isolation model verifiable rather than assumed.

**This suite blocks merge** (T-1). It is the only test category with that status
in its own right, and every new data access pattern extends it.

---

## AI Evaluation Testing

Non-deterministic output cannot be tested with conventional assertions. A
separate lane with different mechanics (`10`).

| Layer | Type | When | Gate |
| --- | --- | --- | --- |
| Schema conformance | Deterministic | Every call, including production | Hard failure |
| Structural assertions | Deterministic | Every eval run | Hard failure |
| Golden set scoring | Statistical | On prompt/model change | Regression threshold |
| LLM-as-judge rubrics | Statistical | On prompt/model change | Regression threshold |
| Adversarial / injection (SEC-9) | Deterministic | Every CI run | Hard failure |
| Human sampling | Qualitative | Weekly | Calibration, advisory |

**Why statistical gating rather than pass/fail** (D-96): identical inputs
produce varying outputs, so per-run assertions on generated prose are flaky by
construction — and flaky tests get ignored (principle 3). Gating on aggregate
scores across a golden set, with a regression tolerance, gives a stable signal
about a genuinely unstable system.

**Deterministic layers are gated hard** because they *are* deterministic: schema
conformance, required structural elements, resolvable traceability links, and
injection resistance either hold or do not.

**Evaluation runs are not part of the standard PR pipeline** — they are slower
and cost real money. They run on changes to prompts, workflows, model
configuration or retrieval, and nightly on `main`.

---

## Performance and Load Testing

| Type | Purpose | Frequency |
| --- | --- | --- |
| Endpoint benchmarks | Guard P-1/P-2 against regression | Per PR on critical endpoints |
| Load tests | Verify S-3/S-4 sustained targets | Before each phase milestone |
| Spike tests | Verify S-5 burst handling | Before each phase milestone |
| Soak tests | Detect leaks and degradation over time | Quarterly |
| Graph traversal benchmarks | Verify P-3 at realistic volume | On graph changes |
| Query performance tests | Detect N+1 and missing indexes | Per PR (D-134) |

**Load tests use realistic multi-tenant distribution** — many small tenants, a
few large ones — not uniform load. Uniform load hides exactly the noisy-neighbour
and skew problems that S-9 exists to prevent, and produces reassuring numbers
that do not survive production.

---

## Security Testing

| Type | Method | Frequency |
| --- | --- | --- |
| SAST | Static analysis | Every PR, blocking |
| SCA | Dependency scanning | Every PR + daily, blocking on critical/high |
| Secret scanning | Pattern + entropy | Every commit, blocking |
| IaC scanning | Terraform policy checks | Every PR |
| Container scanning | Image vulnerability scan | Every build |
| Authorization tests | Automated negative cases | Every PR |
| Prompt injection suite | Adversarial corpus | Every PR |
| Penetration test | Third party | Annual (SEC-10) |

---

## Test Data

- **Factories, not fixtures.** Fixture files drift from the schema and become a
  maintenance burden; factories with sensible defaults and explicit overrides
  keep tests readable and let each test state only what it cares about.
- **Every test creates its own data.** No shared mutable state between tests, so
  tests can run in any order and in parallel.
- **Multi-tenant by default** — the standard test scenario includes at least two
  tenants, so a missing tenant scope fails immediately rather than passing in a
  single-tenant test and leaking in production.
- **No production data**, ever (D-114).
- Synthetic generators produce realistic volume and shape for performance tests.

---

## Suite Health

The suite is infrastructure and is maintained as such.

| Metric | Target | Response when breached |
| --- | --- | --- |
| Unit suite duration | < 60 s | Investigate; probable hidden IO |
| Full PR pipeline | < 10 min (M-4) | Parallelize; apply affected-target detection |
| Flake rate | < 0.5% | Quarantine immediately; fix or delete |
| Domain coverage | > 85% (M-3) | Block merge |

**On flaky tests specifically:** quarantine is immediate and automatic. A
quarantined test does not block merge but does raise a tracked defect with an
owner. Leaving a flaky test in the blocking suite is how a team learns to
re-run CI reflexively — and the day a real failure appears, it gets re-run too.

---

## What We Do Not Test

Stated explicitly, because untargeted testing wastes effort and dilutes the
suite:

| Not tested | Reason |
| --- | --- |
| Framework internals | Tested by their maintainers |
| Third-party library behaviour | Not our code; we test our *usage* at integration boundaries |
| Trivial accessors | No logic, no risk, pure coverage theatre |
| Exact AI prose output | Non-deterministic by nature; we test structure and score quality |
| Visual pixel-perfection | High maintenance, low value; accessibility and interaction are tested instead |

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-137 | Unit tests target the domain layer; coverage measured there (M-3) | Global coverage targets get gamed; the domain is where our thinking lives |
| D-138 | Integration tests run against real Postgres and Redis | SQLite cannot test RLS; a different database is a different system |
| D-139 | Every endpoint has a negative authorization test | The denial case is the one that matters and the one usually omitted |
| D-140 | Tenant isolation is a separate blocking test category | Only unrecoverable failure class |
| D-141 | Isolation suite explicitly proves RLS is active and the role is unprivileged | Every other isolation test passes while RLS is silently inert |
| D-142 | AI evaluation gated on aggregate regression, not per-run pass/fail | Non-determinism makes per-run gating flaky, and flaky gates get ignored |
| D-143 | AI evals run on AI-affecting changes and nightly, not every PR | Slow and costs real money |
| D-144 | E2E suite capped at critical journeys (< 30) | Slow, flaky, expensive; poor substitute for lower levels |
| D-145 | Load tests use skewed multi-tenant distribution | Uniform load hides the skew problems S-9 exists to prevent |
| D-146 | Factories over fixtures; every test creates its own data | Enables parallel, order-independent tests |
| D-147 | Test scenarios are multi-tenant by default | A missing tenant scope must fail immediately |
| D-148 | Flaky tests quarantined automatically and immediately | Tolerated flakiness teaches the team to ignore red builds |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Coverage pursued as a number rather than confidence | False security; tested getters, untested logic | Domain-scoped target; review judges whether tests assert meaningful behaviour |
| Isolation suite misses a novel access path | Undetected leak | Extended with every new access pattern; annual pentest as independent check |
| AI evaluation costs grow with golden set size | Budget pressure; temptation to skip | Tiered sets — fast subset per change, full nightly |
| E2E suite grows and slows the pipeline | M-4 breached; batching returns | Hard cap on scenarios; additions require justification |
| Integration tests slow as data volume grows | Slower feedback | Parallelization; transaction rollback isolation; targeted data volumes |
| Tests coupled to implementation block refactoring | The architecture cannot self-correct | Behaviour-focused testing; refactors that break many tests are a review signal |

## Dependencies

- **Depends on:** NFRs (`04`), tenancy (`07`), security (`09`), AI strategy
  (`10`), coding standards (`13`).
- **Depended on by:** quality gates, definition of done, deployment strategy.

## Future Improvements

- Write the isolation suite specification before the first tenant-scoped table
  exists — the tests should predate the schema.
- Add mutation testing on the domain layer once it stabilizes, to verify tests
  actually detect defects rather than merely executing code.
- Introduce a performance regression baseline per endpoint once production
  traffic provides realistic profiles.
