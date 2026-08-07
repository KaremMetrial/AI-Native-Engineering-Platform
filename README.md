# AI-Native Engineering Platform

An AI-native SaaS platform that lets software companies take an idea from a
single sentence to a delivered, maintained product — keeping every artifact
(discovery, BRD, SRS, architecture, estimate, proposal, contract, task, test,
deployment) linked in one traceable graph, with AI as a governed participant in
the lifecycle.

**Status: engineering foundation complete, pending review. No implementation
has begun.**

## Start Here

| If you want to… | Read |
| --- | --- |
| Understand the standards all work is held to | [Engineering Charter](docs/engineering/MASTER_SYSTEM_PROMPT.md) |
| Understand the project in about an hour | [Docs index](docs/README.md) → docs `01`, `03`, `05`, `21` |
| Know what matters most and where this plan could fail | [Final Recommendations](docs/governance/21-final-recommendations.md) |
| Know what gets built when | [Roadmap](docs/governance/20-roadmap.md) |

## Engineering Charter

Every change to this repository — human-authored or AI-authored — is governed
by the **[Master System Prompt](docs/engineering/MASTER_SYSTEM_PROMPT.md)**. It
defines the operating role, the quality bar, the principles to prefer, and the
rules that are never negotiable.

Read it before contributing. It is the standard code review is conducted
against. AI coding agents pick it up automatically: [`CLAUDE.md`](CLAUDE.md)
imports it at the repository root.

## Engineering Foundation

The [documentation set](docs/README.md) records the full foundation — product
strategy, architecture, and delivery practice — with the reasoning, trade-offs
and rejected alternatives behind every decision.

| Area | Contents |
| --- | --- |
| [Product](docs/README.md#product) | Vision, business goals, personas, bounded contexts, phased scope |
| [Architecture](docs/README.md#architecture) | NFRs, high-level architecture, technology decisions, multi-tenancy, scalability, security, AI strategy |
| [Architecture design](docs/README.md#architecture--detailed-design) | C4 context/containers/components, DDD and context map, communication, events, data, caching and queues, observability, AI integration, resilience |
| [Architecture data](docs/README.md#architecture--data) | Entity model and ownership, read/write models, data flow, indexing and partitioning, retention and soft delete, audit, GDPR, backup and DR |
| [Architecture AI](docs/README.md#architecture--ai) | Multi-provider (Claude/GPT/Gemini/DeepSeek), gateway, prompt engine and library, PromptOps, context/memory/knowledge engines, bounded agents, evaluation, AI security |
| [Delivery](docs/README.md#delivery) | Repository layout, development, coding standards, testing, deployment, review gates, definition of done, documentation |
| [Governance](docs/README.md#governance) | Risk register, roadmap, final recommendations |
| [Foundation](docs/README.md#foundation--method-and-durability) | Engineering principles, architecture philosophy, technology selection, naming, performance, versioning, future evolution |

Where the first four areas record *what we decided*, Foundation records *how
decisions are made* and how the system stays changeable for a decade. A
[topic coverage map](docs/README.md#topic-coverage-map) shows where each
foundation subject is addressed.

Decisions are numbered globally (`D-01`…`D-663`) so they can be cited precisely
from code review, commit messages, and decision records.

## Repository Layout

| Path | Purpose |
| --- | --- |
| `CLAUDE.md` | AI agent entry point; imports the charter |
| `docs/engineering/MASTER_SYSTEM_PROMPT.md` | The engineering charter. Canonical, versioned. |
| `docs/README.md` | Documentation index and reading order |
| `docs/product/` | Vision, personas, modules and scope |
| `docs/architecture/` | NFRs, architecture, technology, tenancy, scalability, security, AI |
| `docs/architecture/design/` | C4 design set: context, containers, components, DDD, communication, events, data, observability, resilience |
| `docs/architecture/data/` | Data architecture: entities and ownership, read/write models, data flow, indexes, retention, audit, GDPR, backup and DR |
| `docs/architecture/ai/` | AI architecture: multi-provider, gateway, prompts, PromptOps, context/memory/knowledge, workflows and agents, evaluation, AI security |
| `docs/architecture/adr/` | Architectural decision records (from implementation onward) |
| `docs/delivery/` | Development, standards, testing, deployment, review, done, documentation |
| `docs/governance/` | Risks, roadmap, recommendations |
| `docs/foundation/` | Principles, architecture philosophy, technology selection, naming, performance, versioning, evolution |

Application code will be added under `apps/`, `packages/` and `infra/` per
[the repository strategy](docs/delivery/11-repository-and-folder-strategy.md)
once the foundation is approved and Phase 0 begins.

## Contributing

- **Standards:** [coding standards](docs/delivery/13-coding-standards-strategy.md),
  enforced mechanically in CI.
- **Review:** [review process and quality gates](docs/delivery/16-review-process-and-quality-gates.md).
- **Completion:** [definition of done](docs/delivery/17-definition-of-done.md).
- **Significant decisions:** require an [ADR](docs/architecture/adr/README.md),
  merged before implementation.

## Changing the Charter

The charter is versioned. Any amendment must, in a single commit:

1. Update the text.
2. Bump the version in the metadata table.
3. Add a row to the revision history explaining the change.

Foundation documents change by pull request and are reviewed at every phase
boundary. Reversing a numbered decision requires an ADR that supersedes it.
