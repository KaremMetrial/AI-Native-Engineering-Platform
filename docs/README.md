# Engineering Documentation

The complete engineering foundation for the AI-Native Engineering Platform:
what we are building, why it is built this way, and the standards it is built
to.

**Status:** Foundation complete. Phase 0 implementation in progress — see the
root [`README.md`](../README.md#development) for the monorepo scaffold and
verified commands, and `governance/20-roadmap.md` for what's left.

---

## Reading Order

New to the project? Read `01`, `03`, `05`, then `21`. That is roughly an hour
and covers the strategy, the shape of the system, and what matters most. Read
the rest as the work requires.

Making an architectural or technology decision? Read `22` and `23` first — they
govern *how* decisions are made here, and `24` governs how technology enters and
leaves.

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

### Architecture — detailed design

The C4-structured design. Where `04`–`10` set architectural strategy, these
specify the system that implements it.

| Doc | Covers |
| --- | --- |
| [29 · System Context](architecture/design/29-system-context.md) | C4 L1 — actors, external systems, trust zones, perimeter data flows, criticality classification |
| [30 · Container Architecture](architecture/design/30-container-architecture.md) | C4 L2 — every deployable and data container: responsibility, technology, interfaces, scaling, failure impact |
| [31 · Component Architecture](architecture/design/31-component-architecture.md) | C4 L3 — Core API, AI service, workers, realtime gateway internals; request and job pipelines |
| [32 · Domain Model and DDD](architecture/design/32-domain-model-and-ddd.md) | Aggregate design rules, consistency boundaries, tactical patterns, the Delivery Graph model |
| [33 · Context Map and Service Boundaries](architecture/design/33-context-map-and-service-boundaries.md) | Strategic DDD — integration patterns between contexts, and when a module becomes a service |
| [34 · Communication Architecture](architecture/design/34-communication-architecture.md) | Sync/async styles, protocols, timeout and retry budgets, idempotency, backpressure, process managers |
| [35 · Event Architecture](architecture/design/35-event-architecture.md) | Event taxonomy, envelope, the transactional outbox, delivery semantics, ordering, DLQ, replay, catalogue |
| [36 · Data and Storage Architecture](architecture/design/36-data-and-storage-architecture.md) | Data tiers, table categories, partitioning, indexing, blobs, vectors, search, retention, tenant deletion |
| [37 · Caching and Queueing](architecture/design/37-caching-and-queueing-architecture.md) | Cache layers, key namespacing, invalidation, stampede protection; queue topology, fairness, retry, DLQ |
| [38 · Observability Architecture](architecture/design/38-observability-architecture.md) | Telemetry pipeline, trace design, async context propagation, log design, metric cardinality, sampling, alerting |
| [39 · Security Architecture](architecture/design/39-security-architecture.md) | Trust zones, network segmentation, token and tenant-context flow, service auth, the untrusted content path |
| [40 · AI Integration Architecture](architecture/design/40-ai-integration-architecture.md) | Workflow execution, context assembly pipeline, provider routing, streaming, guardrails, evaluation, cost metering |
| [41 · Resilience and Failure Recovery](architecture/design/41-resilience-and-failure-recovery.md) | Failure taxonomy, resilience patterns, the degradation matrix, data recovery, reconciliation, failure injection |

### Architecture — data

| Doc | Covers |
| --- | --- |
| [42 · Entity Model and Ownership](architecture/data/42-entity-model-and-ownership.md) | Entity catalogue per context, relationship rules, no cross-context foreign keys, the ownership matrix |
| [43 · Write and Read Models](architecture/data/43-write-and-read-models.md) | Selective CQRS, the write path, read model catalogue, projection mechanics, blue-green rebuilds, consistency budgets |
| [44 · Data Flow Architecture](architecture/data/44-data-flow-architecture.md) | Eight lifecycle stages, ingestion trust and provenance, derivation, serving, export; worked flows for a requirement, an upload and telemetry |
| [45 · Index and Partitioning Strategy](architecture/data/45-index-and-partitioning-strategy.md) | Index principles and types, access-pattern map, index budget, partition plan and lifecycle, graph traversal escalation path |
| [46 · Retention, Soft Delete and Archiving](architecture/data/46-retention-soft-delete-and-archiving.md) | Why soft delete is not the default, tombstones, retention schedule, hot/warm/cold tiering, archival and restore |
| [47 · Audit and Temporal Data](architecture/data/47-audit-and-temporal-data.md) | Audit vs events vs logs, the audit record model, hash-chained tamper evidence, selective temporal history, tenant-facing audit |
| [48 · GDPR and Data Protection](architecture/data/48-gdpr-and-data-protection.md) | Controller/processor split, personal data localization, subject rights, crypto-shredding to reconcile erasure with immutable audit |
| [49 · Backup, Recovery and DR](architecture/data/49-backup-recovery-and-disaster-recovery.md) | Backup topology per store, RPO/RTO per class, recovery scenarios, tenant-scoped restore, immutable backups, verification |

### Architecture — AI

Multi-provider AI architecture: Claude, GPT, Gemini, DeepSeek and future models,
with the deployment, evaluation and security discipline that makes routing
across them safe. Amends D-51 (genuine multi-provider) and D-89 (bounded agent
steps) — both changes reasoned through in place, not silently overridden.

| Doc | Covers |
| --- | --- |
| [50 · AI Platform and Multi-Provider](architecture/ai/50-ai-platform-and-multi-provider.md) | Subsystem map, the multi-provider decision, model registry, capability modelling, normalization, per-tenant provider governance |
| [51 · AI Gateway](architecture/ai/51-ai-gateway.md) | The mandatory single call path: request pipeline, routing algorithm, caching, rate limiting, failover, streaming, cost accounting |
| [52 · Prompt Engine and Library](architecture/ai/52-prompt-engine-and-library.md) | Structured (never string) prompts, composition, deterministic rendering, cache-aware ordering, token budgeting, the governed library |
| [53 · PromptOps — Versioning, Testing, Review, Deployment](architecture/ai/53-promptops-versioning-testing-review-deployment.md) | Immutable content-addressed versions, five test layers, review checklist, shadow evaluation, progressive rollout, emergency change |
| [54 · PromptOps — Monitoring, Analytics, Cost, Optimization](architecture/ai/54-promptops-monitoring-analytics-cost-optimization.md) | Production quality proxies, drift detection, cost attribution chain, budget hierarchy, the propose-review-canary optimization loop |
| [55 · Context, Memory and Knowledge Engines](architecture/ai/55-context-memory-and-knowledge-engines.md) | Why the three are separate, context assembly and reconciliation, governed durable memory against poisoning, tiered knowledge, hybrid retrieval |
| [56 · Workflow and Agent Engine](architecture/ai/56-workflow-and-agent-engine.md) | The bounded-agent-step amendment to D-89, engine-controlled tool loop, tool registry, multi-agent coordination via the workflow graph |
| [57 · Evaluation System](architecture/ai/57-evaluation-system.md) | Golden set architecture, decomposed rubric judges and their calibration, cross-provider evaluation, agent-step evaluation, human-in-the-loop |
| [58 · AI and Prompt Security](architecture/ai/58-ai-and-prompt-security.md) | Injection defence across providers and agents, prompt library protection, provider data governance, abuse prevention, isolation as composition |

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
| [19 · Risk Register](governance/19-risk-register.md) | 26 risks across engineering, technical, operational and business, each with an early-warning signal |
| [20 · Roadmap](governance/20-roadmap.md) | Phases 0–4 with objectives, deliverables and exit criteria |
| [21 · Final Recommendations](governance/21-final-recommendations.md) | Load-bearing decisions, assumptions to validate, non-negotiables, next steps |
| [22 · Graph Traversal Benchmark Results](governance/22-graph-traversal-benchmark-results.md) | Phase 0 spike result: P-3 measured at p95 2.57ms against a 1s budget, resolving TR-2 |

### Foundation — method and durability

Where the documents above record *what we decided*, these record *how decisions
are made and how the system stays changeable for a decade*.

| Doc | Covers |
| --- | --- |
| [22 · Engineering Principles](foundation/22-engineering-principles.md) | 17 principles in three tiers, each with its reasoning, alternatives, cost and **boundary** — plus how conflicts between them are resolved |
| [23 · Architecture Philosophy](foundation/23-architecture-philosophy.md) | Reversibility as the governing criterion, ranked characteristics and explicit sacrifices, coupling by kind, complexity budget, architecture as executable constraint |
| [24 · Technology Selection Strategy](foundation/24-technology-selection-strategy.md) | Innovation budget, four-ring radar, adoption process, dependency policy, build/buy/adopt, upgrade cadence, and how technology **leaves** |
| [25 · Naming Standards](foundation/25-naming-standards.md) | Ubiquitous language, cross-layer consistency, conventions per artifact, anti-patterns, and the graded rename policy |
| [26 · Performance Strategy](foundation/26-performance-strategy.md) | Decomposed latency budgets, measure-before-optimize, tail latency, perceived performance, caching discipline, when *not* to optimize |
| [27 · Versioning Strategy](foundation/27-versioning-strategy.md) | What needs versioning and what does not, API versioning, deprecation policy, schema and event evolution, prompt and model pinning |
| [28 · Future Evolution Strategy](foundation/28-future-evolution-strategy.md) | Ten-year maintainability defined and measured, the reversibility ledger, technology refresh, strangler replacement, drift detection, knowledge continuity |

---

## Topic Coverage Map

Where each foundation topic is addressed. Several topics are covered by one
document and refined by another — the "also" column shows where.

| Topic | Primary | Also |
| --- | --- | --- |
| Engineering Principles | [22](foundation/22-engineering-principles.md) | Charter |
| Architecture Philosophy | [23](foundation/23-architecture-philosophy.md) | [05](architecture/05-high-level-architecture.md) |
| Technology Selection Strategy | [24](foundation/24-technology-selection-strategy.md) | [06](architecture/06-technology-decisions.md) |
| Development Workflow | [12](delivery/12-development-strategy.md) | [15](delivery/15-deployment-strategy.md) |
| Repository Strategy | [11](delivery/11-repository-and-folder-strategy.md) | — |
| Branching Strategy | [12](delivery/12-development-strategy.md) | — |
| Coding Standards | [13](delivery/13-coding-standards-strategy.md) | [22](foundation/22-engineering-principles.md) |
| Naming Standards | [25](foundation/25-naming-standards.md) | [11](delivery/11-repository-and-folder-strategy.md) |
| Folder Structure Philosophy | [11](delivery/11-repository-and-folder-strategy.md) | [23](foundation/23-architecture-philosophy.md) |
| Quality Gates | [16](delivery/16-review-process-and-quality-gates.md) | [15](delivery/15-deployment-strategy.md) |
| Definition of Done | [17](delivery/17-definition-of-done.md) | — |
| Review Process | [16](delivery/16-review-process-and-quality-gates.md) | — |
| Testing Strategy | [14](delivery/14-testing-strategy.md) | [10](architecture/10-ai-strategy.md) |
| Deployment Strategy | [15](delivery/15-deployment-strategy.md) | [12](delivery/12-development-strategy.md) |
| Security Strategy | [09](architecture/09-security-strategy.md) | [07](architecture/07-multi-tenancy-strategy.md) |
| Scalability Strategy | [08](architecture/08-scalability-strategy.md) | [04](architecture/04-non-functional-requirements.md) |
| Performance Strategy | [26](foundation/26-performance-strategy.md) | [04](architecture/04-non-functional-requirements.md), [08](architecture/08-scalability-strategy.md) |
| Documentation Strategy | [18](delivery/18-documentation-strategy.md) | — |
| Versioning Strategy | [27](foundation/27-versioning-strategy.md) | [12](delivery/12-development-strategy.md) |
| Future Evolution Strategy | [28](foundation/28-future-evolution-strategy.md) | [23](foundation/23-architecture-philosophy.md), [24](foundation/24-technology-selection-strategy.md) |

### Architecture design topics

| Topic | Primary | Also |
| --- | --- | --- |
| High Level Architecture | [05](architecture/05-high-level-architecture.md) | [30](architecture/design/30-container-architecture.md) |
| System Context | [29](architecture/design/29-system-context.md) | — |
| Containers | [30](architecture/design/30-container-architecture.md) | [15](delivery/15-deployment-strategy.md) |
| Components | [31](architecture/design/31-component-architecture.md) | — |
| Modules | [03](product/03-core-modules-and-scope.md) | [11](delivery/11-repository-and-folder-strategy.md), [31](architecture/design/31-component-architecture.md) |
| DDD | [32](architecture/design/32-domain-model-and-ddd.md) | [33](architecture/design/33-context-map-and-service-boundaries.md) |
| Bounded Contexts | [03](product/03-core-modules-and-scope.md) | [33](architecture/design/33-context-map-and-service-boundaries.md) |
| Service Boundaries | [33](architecture/design/33-context-map-and-service-boundaries.md) | [05](architecture/05-high-level-architecture.md) |
| Communication | [34](architecture/design/34-communication-architecture.md) | [30](architecture/design/30-container-architecture.md) |
| Events | [35](architecture/design/35-event-architecture.md) | [27](foundation/27-versioning-strategy.md) |
| Caching | [37](architecture/design/37-caching-and-queueing-architecture.md) | [26](foundation/26-performance-strategy.md), [08](architecture/08-scalability-strategy.md) |
| Queues | [37](architecture/design/37-caching-and-queueing-architecture.md) | [30](architecture/design/30-container-architecture.md) |
| Storage | [36](architecture/design/36-data-and-storage-architecture.md) | [07](architecture/07-multi-tenancy-strategy.md) |
| Search | [36](architecture/design/36-data-and-storage-architecture.md) | [06](architecture/06-technology-decisions.md) |
| Logging | [38](architecture/design/38-observability-architecture.md) | [09](architecture/09-security-strategy.md) |
| Monitoring | [38](architecture/design/38-observability-architecture.md) | [15](delivery/15-deployment-strategy.md) |
| Observability | [38](architecture/design/38-observability-architecture.md) | [04](architecture/04-non-functional-requirements.md) |
| Security | [39](architecture/design/39-security-architecture.md) | [09](architecture/09-security-strategy.md), [07](architecture/07-multi-tenancy-strategy.md) |
| AI Integration | [40](architecture/design/40-ai-integration-architecture.md) | [10](architecture/10-ai-strategy.md) |
| Failure Recovery | [41](architecture/design/41-resilience-and-failure-recovery.md) | [15](delivery/15-deployment-strategy.md) |

### Data architecture topics

| Topic | Primary | Also |
| --- | --- | --- |
| Entities | [42](architecture/data/42-entity-model-and-ownership.md) | [32](architecture/design/32-domain-model-and-ddd.md) |
| Aggregates | [32](architecture/design/32-domain-model-and-ddd.md) | [43](architecture/data/43-write-and-read-models.md) |
| Relationships | [42](architecture/data/42-entity-model-and-ownership.md) | [32](architecture/design/32-domain-model-and-ddd.md) |
| Ownership | [42](architecture/data/42-entity-model-and-ownership.md) | [33](architecture/design/33-context-map-and-service-boundaries.md) |
| Data Flow | [44](architecture/data/44-data-flow-architecture.md) | [35](architecture/design/35-event-architecture.md) |
| Write Models | [43](architecture/data/43-write-and-read-models.md) | [32](architecture/design/32-domain-model-and-ddd.md) |
| Read Models | [43](architecture/data/43-write-and-read-models.md) | [36](architecture/design/36-data-and-storage-architecture.md) |
| Indexes Strategy | [45](architecture/data/45-index-and-partitioning-strategy.md) | [36](architecture/design/36-data-and-storage-architecture.md) |
| Partitioning Strategy | [45](architecture/data/45-index-and-partitioning-strategy.md) | [08](architecture/08-scalability-strategy.md) |
| Archiving Strategy | [46](architecture/data/46-retention-soft-delete-and-archiving.md) | — |
| Soft Delete Strategy | [46](architecture/data/46-retention-soft-delete-and-archiving.md) | — |
| Data Retention | [46](architecture/data/46-retention-soft-delete-and-archiving.md) | [36](architecture/design/36-data-and-storage-architecture.md) |
| Audit Strategy | [47](architecture/data/47-audit-and-temporal-data.md) | [09](architecture/09-security-strategy.md) |
| Backup Strategy | [49](architecture/data/49-backup-recovery-and-disaster-recovery.md) | [15](delivery/15-deployment-strategy.md) |
| Disaster Recovery | [49](architecture/data/49-backup-recovery-and-disaster-recovery.md) | [41](architecture/design/41-resilience-and-failure-recovery.md) |
| GDPR readiness | [48](architecture/data/48-gdpr-and-data-protection.md) | [09](architecture/09-security-strategy.md) |
| Multi Tenant Strategy | [07](architecture/07-multi-tenancy-strategy.md) | [42](architecture/data/42-entity-model-and-ownership.md), [36](architecture/design/36-data-and-storage-architecture.md) |
| Bounded Contexts | [03](product/03-core-modules-and-scope.md) | [33](architecture/design/33-context-map-and-service-boundaries.md), [42](architecture/data/42-entity-model-and-ownership.md) |
| Performance considerations | [45](architecture/data/45-index-and-partitioning-strategy.md) | [26](foundation/26-performance-strategy.md) |
| Future scalability | [08](architecture/08-scalability-strategy.md) | [45](architecture/data/45-index-and-partitioning-strategy.md), [46](architecture/data/46-retention-soft-delete-and-archiving.md) |

### AI architecture topics

| Topic | Primary | Also |
| --- | --- | --- |
| Claude / GPT / Gemini / DeepSeek / future models | [50](architecture/ai/50-ai-platform-and-multi-provider.md) | [06](architecture/06-technology-decisions.md) |
| AI Gateway | [51](architecture/ai/51-ai-gateway.md) | [40](architecture/design/40-ai-integration-architecture.md) |
| Prompt Engine | [52](architecture/ai/52-prompt-engine-and-library.md) | — |
| Prompt Library | [52](architecture/ai/52-prompt-engine-and-library.md) | — |
| Context Engine | [55](architecture/ai/55-context-memory-and-knowledge-engines.md) | [40](architecture/design/40-ai-integration-architecture.md) |
| Memory Engine | [55](architecture/ai/55-context-memory-and-knowledge-engines.md) | — |
| Knowledge Engine | [55](architecture/ai/55-context-memory-and-knowledge-engines.md) | [36](architecture/design/36-data-and-storage-architecture.md) |
| Workflow Engine | [56](architecture/ai/56-workflow-and-agent-engine.md) | [40](architecture/design/40-ai-integration-architecture.md) |
| Agent System | [56](architecture/ai/56-workflow-and-agent-engine.md) | [10](architecture/10-ai-strategy.md) |
| Evaluation System | [57](architecture/ai/57-evaluation-system.md) | [10](architecture/10-ai-strategy.md) |
| Prompt Versioning | [53](architecture/ai/53-promptops-versioning-testing-review-deployment.md) | [27](foundation/27-versioning-strategy.md) |
| Prompt Testing | [53](architecture/ai/53-promptops-versioning-testing-review-deployment.md) | [57](architecture/ai/57-evaluation-system.md) |
| Prompt Review | [53](architecture/ai/53-promptops-versioning-testing-review-deployment.md) | [16](delivery/16-review-process-and-quality-gates.md) |
| Prompt Deployment | [53](architecture/ai/53-promptops-versioning-testing-review-deployment.md) | [15](delivery/15-deployment-strategy.md) |
| Prompt Monitoring | [54](architecture/ai/54-promptops-monitoring-analytics-cost-optimization.md) | [38](architecture/design/38-observability-architecture.md) |
| Prompt Analytics | [54](architecture/ai/54-promptops-monitoring-analytics-cost-optimization.md) | — |
| Prompt Cost Tracking | [54](architecture/ai/54-promptops-monitoring-analytics-cost-optimization.md) | — |
| Prompt Security | [58](architecture/ai/58-ai-and-prompt-security.md) | [09](architecture/09-security-strategy.md), [39](architecture/design/39-security-architecture.md) |
| Prompt Caching | [51](architecture/ai/51-ai-gateway.md) | [52](architecture/ai/52-prompt-engine-and-library.md) |
| Prompt Routing | [51](architecture/ai/51-ai-gateway.md) | [50](architecture/ai/50-ai-platform-and-multi-provider.md) |
| Prompt Optimization | [54](architecture/ai/54-promptops-monitoring-analytics-cost-optimization.md) | — |

---

## Conventions

**Every document carries the same six sections** — Purpose, Scope, Decisions,
Risks, Dependencies, Future Improvements. Purpose and Scope let you judge
relevance in seconds; Scope also states what a document deliberately does *not*
cover, which is how the set avoids duplicating itself.

**Decisions are numbered globally** (`D-01` … `D-665`) so they can be cited
precisely from anywhere — other documents, ADRs, code review, commit messages.
Principles carry `P-n` identifiers in [22](foundation/22-engineering-principles.md).

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
