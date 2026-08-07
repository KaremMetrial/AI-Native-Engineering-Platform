# CLAUDE.md

## Engineering Charter

The engineering charter below is binding for every change made to this
repository. Read it before planning or writing any code.

@docs/engineering/MASTER_SYSTEM_PROMPT.md

## Engineering Foundation

The charter sets the standard; `docs/` records the decisions. Before working on
anything non-trivial, read the documents relevant to it — start from
`docs/README.md`, which lists every document in the set with what each covers.

Load-bearing constraints that apply to nearly all work:

- **Tenant isolation** (`docs/architecture/07-multi-tenancy-strategy.md`) — every
  tenant-scoped table carries `tenant_id`, isolation is enforced by Postgres
  RLS, and tenant context is never taken from client input. Isolation tests
  block merge.
- **No AI inference in a synchronous request path** (`docs/architecture/04-non-functional-requirements.md`,
  P-9) — all AI work is queued.
- **AI produces drafts; human approval confers authority**
  (`docs/architecture/10-ai-strategy.md`).
- **Module boundaries** (`docs/product/03-core-modules-and-scope.md`,
  `docs/delivery/11-repository-and-folder-strategy.md`) — cross-module access
  goes through a module's Application layer or a domain event, never its
  internals. Enforced by static analysis.
- **Coding standards** (`docs/delivery/13-coding-standards-strategy.md`) apply
  identically to AI-generated code. Framework-idiomatic layouts that violate the
  deliberate layering are the most common failure mode — check architectural fit,
  not just passing tests.
- **Definition of done** (`docs/delivery/17-definition-of-done.md`) — work is not
  done at "code written" or "merged".

The detailed design lives in `docs/architecture/design/` (C4 context, containers
and components; DDD and context map; communication, events, data, caching and
queues, observability, security, AI integration, resilience),
`docs/architecture/data/` (entity model and ownership, read/write models, data
flow, indexes and partitioning, retention and soft delete, audit, GDPR, backup
and DR), and `docs/architecture/ai/` (multi-provider gateway, prompt engine and
library, PromptOps, context/memory/knowledge engines, bounded agents,
evaluation, AI security). Read the documents covering the area you are changing
before changing it.

Two AI-architecture decisions amend earlier ones and each requires its own ADR
before implementation, per `docs/architecture/ai/50-ai-platform-and-multi-provider.md`:
genuine multi-provider support (amends D-51) and bounded agent steps within
workflows (amends D-89, see `docs/architecture/ai/56-workflow-and-agent-engine.md`).

Three data rules are violated most often and are worth knowing before you touch
the schema: **no foreign keys across bounded-context boundaries** (D-461), **soft
delete is not the default and always pairs with a sweeper** (D-502), and
**personal data is localized, never denormalized into other tables** (D-528).

Before making an architectural or technology decision, read
`docs/foundation/23-architecture-philosophy.md` and
`docs/foundation/24-technology-selection-strategy.md` — they govern how such
decisions are made here, including the reversal-cost classification that
determines how much rigour a decision needs.
`docs/foundation/22-engineering-principles.md` states the principles *and their
boundaries*; cite the boundary, not just the principle.

Decisions are numbered globally (`D-nn`). Cite them when a change depends on or
contradicts one. Reversing a numbered decision requires an ADR
(`docs/architecture/adr/README.md`) merged before the implementation.

## Repository Conventions

The charter is the single source of truth for standards. This section carries
only what is specific to this repository and cannot be derived from the
charter.

- The charter lives at `docs/engineering/MASTER_SYSTEM_PROMPT.md` and is
  imported here. Change it in that file only — never restate its rules
  elsewhere, in this file or in code comments.
- Any change to the charter is a versioned change: bump the version in its
  metadata table and add a row to its revision history in the same commit.
- Documentation changes in the same pull request as the code it describes.
- Build, test, and lint commands are documented here as each part of the
  platform lands, alongside the code that introduces them. A command is
  documented only once it exists and passes. **Phase 0 is in progress** — the
  monorepo scaffold (`apps/api`, `apps/web`, `apps/ai`) is real and gated; the
  verified commands are listed in `README.md`'s Development section.
