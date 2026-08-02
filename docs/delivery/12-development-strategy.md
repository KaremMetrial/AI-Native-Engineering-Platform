# Development Strategy

## Purpose

Define how work flows from idea to production: branching, environments, local
development, work decomposition, and the practices that keep a small team fast
without accumulating debt that a larger team must repay.

## Scope

**In scope:** branching model, environment topology, local development,
feature flags, database change management, work decomposition, team structure,
and technical debt management.

**Out of scope:** CI/CD mechanics (`15`), review mechanics (`16`), coding style
(`13`).

---

## Branching: Trunk-Based Development

**Decision:** short-lived branches merged to `main` at least daily; `main` is
always releasable.

### Why

- **Small changes are safer changes.** A one-day branch produces a reviewable
  diff, an understandable revert, and a bounded blast radius. A two-week branch
  produces a review nobody performs properly and a merge conflict nobody can
  resolve confidently.
- **Continuous integration in the literal sense** — integration problems surface
  in hours, not at the end of a sprint when they are expensive and everyone is
  under deadline pressure.
- **Enables continuous deployment**, which shortens the feedback loop that
  makes everything else in this strategy work.
- **Avoids merge debt**, which grows super-linearly with branch age.

### Requirements this imposes

Trunk-based development is not merely a branching preference; it demands
supporting practices, and without them it fails badly:

- **Feature flags** so incomplete work merges safely (below).
- **Fast, trustworthy CI** (M-4). Slow CI causes batching, which destroys the
  model.
- **Comprehensive automated tests.** Without them, "always releasable" is a
  claim rather than a fact.
- **Expand-contract database changes** so schema changes are backward
  compatible (below).

### Alternatives considered

| Model | Why not chosen |
| --- | --- |
| **Git Flow** | Designed for versioned, infrequently released software. Long-lived develop/release branches create merge debt and slow feedback. Wrong tool for continuously deployed SaaS. |
| **GitHub Flow with long-lived feature branches** | Superficially similar but permits week-long branches — reintroducing large diffs and integration pain. |
| **Release branches** | Adds cherry-pick overhead and divergence. Revisit only if we ever ship on-premise versions (currently out of scope). |

**Trade-off accepted:** trunk-based development demands more discipline —
smaller changes, flag hygiene, backward-compatible migrations. That discipline
is the point; it is also what makes the codebase safe for AI-assisted
contribution at volume.

### Branch conventions

- `main` — always releasable, protected, no direct pushes.
- `feat/<short-description>`, `fix/…`, `chore/…`, `docs/…` — short-lived.
- Squash merge to `main`: one logical change, one commit, clean revert.
- Conventional Commits for machine-readable history and automated changelogs.

---

## Environments

| Environment | Purpose | Data | Deploy |
| --- | --- | --- | --- |
| **Local** | Development | Seeded synthetic | On save |
| **Preview** | Per-PR validation, ephemeral | Seeded synthetic | Automatic per PR |
| **Staging** | Pre-production verification | Anonymized or synthetic | Automatic from `main` |
| **Production** | Live | Real | Promoted from staging |

**Preview environments are high-leverage.** Reviewing a running instance of a
change catches what code review cannot — interaction problems, visual
regressions, unclear flows — and lets non-engineers (product, design) validate
before merge rather than after release. Ephemeral and destroyed on PR close, so
cost is proportional to open PRs.

**Production data is never copied to lower environments.** Not even anonymized,
unless the anonymization is verified. It contains our customers' clients'
confidential information (`09`'s threat model), and every copy multiplies the
exposure surface. Synthetic data generation is a deliberate investment,
maintained as first-class tooling — quality synthetic data also makes local
development and testing genuinely better.

---

## Local Development

**Target: clone to running system in under 30 minutes, one command.** Directly
supports M-5.

- Containerized dependencies (Postgres, Redis) so versions match production.
- Seed data covering realistic multi-tenant scenarios — including at least two
  tenants, so tenant-scoping bugs surface locally rather than in staging.
- AI provider calls stubbed by default with recorded fixtures; real calls behind
  an explicit opt-in flag. Prevents accidental spend and keeps tests fast and
  deterministic.
- Pre-commit hooks for formatting and fast lint checks — fast feedback locally,
  authoritative verification in CI. Hooks must be fast; slow hooks get bypassed
  with `--no-verify` and stop protecting anything.

---

## Feature Flags

The mechanism that makes trunk-based development safe.

| Type | Purpose | Lifetime |
| --- | --- | --- |
| **Release** | Merge incomplete work disabled | Days to weeks; removed on launch |
| **Operational** | Kill switches for expensive or risky subsystems | Permanent |
| **Permission/entitlement** | Plan-tier gating | Permanent |
| **Experiment** | A/B comparison | Duration of the experiment |

**Discipline — flags are technical debt with a due date:**

- Every release flag has an owner and a removal date at creation.
- Stale flags are surfaced in a report and removed; unlimited flags produce
  combinatorial, untestable state.
- Flags are evaluated in one place, never scattered as ad-hoc conditionals.
- Operational kill switches exist for every AI workflow and every external
  integration — the ability to disable a misbehaving subsystem in seconds,
  without a deploy, is worth more than most incident-response tooling.

---

## Database Change Management

Backward compatibility is mandatory, because deploys are rolling: old and new
application versions run simultaneously against one schema.

**Expand-contract, always:**

1. **Expand** — add the new structure, nullable or defaulted. Deploy. Old code
   still works.
2. **Migrate** — backfill data in batches, monitored. Deploy code that writes
   both old and new.
3. **Switch** — read from the new structure. Deploy.
4. **Contract** — remove the old structure. Deploy.

**Rules:**

- No destructive change in the same deployment as the code that stops using it.
- Migrations are forward-only in production; recovery is a new migration, not a
  rollback. A rollback migration on real data usually loses data.
- Large backfills run as monitored jobs, batched, never as blocking migrations —
  a migration that locks a large table is an outage.
- Every migration is tested against a production-scale dataset before release.

**This is the practice most often skipped and most expensive to skip.** A
single dropped column in the wrong deploy is a production incident during the
rollout window, and the failure is silent until traffic hits the old instances.

---

## Work Decomposition

Work is decomposed so that every merged change is independently valuable and
reviewable.

- **Vertical slices**, not horizontal layers. "Requirement approval end to end"
  ships value; "all repositories for Requirements" ships nothing and cannot be
  validated.
- **Target: under 400 changed lines per PR.** Review quality degrades sharply
  beyond this — large PRs receive approval, not review.
- Refactoring is separated from behaviour change. A PR mixing both is
  unreviewable, because the reviewer cannot distinguish intentional changes from
  incidental ones.
- Architecturally significant decisions require an ADR **before** implementation.

---

## Team Structure

Currently one cross-functional team owning the full stack.

**Explicitly avoided: splitting into frontend and backend teams.** Layer-aligned
teams produce layer-aligned handoffs, and Conway's Law then pushes the
architecture toward the same split — the opposite of the vertical-slice
decomposition above.

As the team grows, split along **bounded context** lines (`03`) rather than
technology lines, so team boundaries reinforce module boundaries instead of
cutting across them. Ownership then maps to CODEOWNERS per module.

**AI orchestration is a specialization, not a separate team**, at least
initially. Isolating AI work in a dedicated team would disconnect it from the
domain it serves — and grounding quality (`10`) depends entirely on domain
understanding.

---

## Technical Debt Management

Debt is inevitable; unmanaged debt is a choice.

- **Recorded, not remembered.** Debt is tracked as work items with the cost of
  carrying it, not left as tribal knowledge or code comments.
- **Charter compliance is not debt.** The absolute rules in the engineering
  charter — no TODOs, no placeholders, no dead code, no ignored warnings — are
  merge conditions. Debt is *deliberate, recorded* trade-offs, not a licence to
  ship incomplete work.
- **Capacity allocation:** roughly 20% of each cycle to debt, tooling and
  reliability. Not a hard rule, but a default that must be argued away rather
  than silently skipped — the failure mode is that it is always deferred for
  one more feature.
- **Debt with compounding cost is prioritized over debt with fixed cost.** Slow
  CI, boundary violations and flaky tests get worse every week and tax every
  future change; an ugly-but-isolated implementation does not.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-111 | Trunk-based development, branches under one day | Small diffs are safer, reviewable and revertible |
| D-112 | `main` always releasable, protected, squash-merged | Clean history, clean reverts |
| D-113 | Ephemeral preview environment per PR | Catches what code review cannot; enables non-engineer validation pre-merge |
| D-114 | Production data never copied to lower environments | Multiplies exposure of customers' clients' confidential data |
| D-115 | Synthetic data generation is first-class tooling | Enables D-114 and improves local development and testing |
| D-116 | AI calls stubbed locally by default | Prevents accidental spend; keeps tests fast and deterministic |
| D-117 | Feature flags mandatory for incomplete work | Precondition for trunk-based development |
| D-118 | Every release flag has an owner and removal date | Unremoved flags create combinatorial untestable state |
| D-119 | Operational kill switch for every AI workflow and integration | Seconds-to-disable beats deploy-to-disable during an incident |
| D-120 | Expand-contract for all schema changes; forward-only migrations | Rolling deploys run two versions against one schema |
| D-121 | Vertical slices; PRs under ~400 lines | Large PRs receive approval, not review |
| D-122 | Refactoring separated from behaviour change | Otherwise the reviewer cannot tell them apart |
| D-123 | Teams split by bounded context, never by technology layer | Conway's Law — team shape becomes architecture shape |
| D-124 | ~20% capacity default for debt, tooling and reliability | Must be argued away, not silently skipped |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Slow CI causes batching and large PRs | Trunk-based development collapses | M-4 tracked as a metric; pipeline speed treated as compounding debt |
| Feature flags accumulate | Combinatorial state; untestable system | Owner and removal date at creation; stale flag reporting |
| Expand-contract skipped under deadline pressure | Production incident during rollout | Migration review checklist; destructive changes flagged automatically in review |
| Preview environment cost grows with open PRs | Unexpected spend | Auto-destroy on PR close; TTL on idle environments |
| Synthetic data diverges from real data shapes | Bugs that only appear in production | Generators updated when the schema changes; migrations tested at production scale |
| 20% debt allocation always deferred | Compounding drag on velocity | Debt items visible in planning; CI duration and flake rate tracked openly |
| Team grows and splits by technology | Architecture follows team shape | D-123 stated now, before the first hire makes it tempting |

## Dependencies

- **Depends on:** architecture (`05`), repository strategy (`11`).
- **Depended on by:** testing strategy, deployment strategy, review process,
  definition of done.

## Future Improvements

- Add automatic detection of potentially destructive migrations in CI.
- Add stale-flag reporting once the flag system is in place.
- Introduce CODEOWNERS per module when the team exceeds one squad.
- Revisit the 20% allocation with measured data on where time actually goes.
