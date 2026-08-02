# Engineering Documentation

The complete engineering foundation for the AI-Native Engineering Platform:
what we are building, why it is built this way, and the standards it is built
to.

**Status:** Foundation complete, pending review. No implementation has begun.

---

## Reading Order

New to the project? Read `01`, `03`, `05`, then `21`. That is roughly an hour
and covers the strategy, the shape of the system, and what matters most. Read
the rest as the work requires.

### Start here

| Doc | Covers |
| --- | --- |
| [Master System Prompt](engineering/MASTER_SYSTEM_PROMPT.md) | The engineering charter — the standard all work is held to |
| [21 · Final Recommendations](governance/21-final-recommendations.md) | The ten decisions that matter most, the unvalidated assumptions, and where this plan is most likely to fail |

### Product

| Doc | Covers |
| --- | --- |
| [01 · Vision, Mission and Goals](product/01-vision-mission-and-goals.md) | Vision, mission, business goals with metrics, market positioning, the strategic bet |
| [02 · Customers and Personas](product/02-customers-and-personas.md) | Target segments, ten personas, and the access model each implies |
| [03 · Core Modules and Scope](product/03-core-modules-and-scope.md) | Fifteen bounded contexts, the Delivery Graph kernel, phased functional scope, explicit exclusions |

### Architecture

| Doc | Covers |
| --- | --- |
| [04 · Non-Functional Requirements](architecture/04-non-functional-requirements.md) | Measurable targets for availability, performance, scale, isolation, security, cost — each with a verification method |
| [05 · High-Level Architecture](architecture/05-high-level-architecture.md) | Modular monolith + extracted AI service, runtime components, key flows, extraction seams |
| [06 · Technology Decisions](architecture/06-technology-decisions.md) | Every stack choice with alternatives, trade-offs, and reversal cost |
| [07 · Multi-Tenancy Strategy](architecture/07-multi-tenancy-strategy.md) | Six-layer isolation, RLS enforcement, authorization model, external stakeholder access |
| [08 · Scalability Strategy](architecture/08-scalability-strategy.md) | Per-tier scaling, staged data strategy with triggers, caching, cost scaling |
| [09 · Security Strategy](architecture/09-security-strategy.md) | Threat model, controls, AI-specific security, secure SDLC, compliance posture |
| [10 · AI Strategy](architecture/10-ai-strategy.md) | Grounding, prompt management, model routing, evaluation, guardrails, cost governance |
| [ADRs](architecture/adr/README.md) | Decision records from implementation onward, and the process for writing them |

### Delivery

| Doc | Covers |
| --- | --- |
| [11 · Repository and Folder Strategy](delivery/11-repository-and-folder-strategy.md) | Monorepo justification, layout, module internals, boundary enforcement |
| [12 · Development Strategy](delivery/12-development-strategy.md) | Trunk-based development, environments, feature flags, expand-contract migrations, team structure |
| [13 · Coding Standards Strategy](delivery/13-coding-standards-strategy.md) | Per-language standards, architectural enforcement, standards for AI-assisted contribution |
| [14 · Testing Strategy](delivery/14-testing-strategy.md) | Test levels, tenant isolation suite, AI evaluation testing, suite health |
| [15 · Deployment Strategy](delivery/15-deployment-strategy.md) | CI/CD, rolling and progressive delivery, infrastructure, observability, disaster recovery |
| [16 · Review Process and Quality Gates](delivery/16-review-process-and-quality-gates.md) | Review expectations, checklists, ADR requirements, the five gates |
| [17 · Definition of Done](delivery/17-definition-of-done.md) | Done for a task, feature, phase, and AI capability — and what done does not mean |
| [18 · Documentation Strategy](delivery/18-documentation-strategy.md) | Documentation types, ownership, currency mechanisms, documentation as agent context |

### Governance

| Doc | Covers |
| --- | --- |
| [19 · Risk Register](governance/19-risk-register.md) | 24 risks across engineering, technical, operational and business, each with an early-warning signal |
| [20 · Roadmap](governance/20-roadmap.md) | Phases 0–4 with objectives, deliverables and exit criteria |
| [21 · Final Recommendations](governance/21-final-recommendations.md) | Load-bearing decisions, assumptions to validate, non-negotiables, next steps |

---

## Conventions

**Every document carries the same six sections** — Purpose, Scope, Decisions,
Risks, Dependencies, Future Improvements. Purpose and Scope let you judge
relevance in seconds; Scope also states what a document deliberately does *not*
cover, which is how the set avoids duplicating itself.

**Decisions are numbered globally** (`D-01` … `D-209`) so they can be cited
precisely from anywhere — other documents, ADRs, code review, commit messages.

**Requirements are numbered by category** in `04`: `A-n` availability, `P-n`
performance, `S-n` scalability, `T-n` tenancy, `SEC-n` security, `C-n`
compliance, `O-n` observability, `$-n` cost, `U-n` usability, `M-n`
maintainability. Business goals are `G1`–`G5` in `01`. Risks are `CR/ER/TR/OR/BR-n`
in `19`.

**Every recommendation carries its reasoning.** A rule without a reason gets
followed until it is inconvenient and then abandoned; a rule with a reason can
be applied intelligently and challenged with evidence.

---

## Changing This Set

- Foundation documents change by pull request, reviewed like code.
- Decisions taken from implementation onward are recorded as
  [ADRs](architecture/adr/README.md), not by rewriting history here.
- The set is reviewed at every phase boundary (D-208).
- Reversing a `D-nn` decision requires an ADR that supersedes it.

**This foundation is a well-reasoned hypothesis, not a proof.** Several of its
central assumptions are explicitly unvalidated and are listed in
[21 · Final Recommendations](governance/21-final-recommendations.md). Phase 0
of the [roadmap](governance/20-roadmap.md) exists to test them before anything
is built on top.
