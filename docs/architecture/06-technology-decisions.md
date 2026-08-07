# Technology Decisions

## Purpose

Record every significant technology choice with its reasoning, alternatives,
trade-offs, and reversal cost. The charter forbids choosing technologies
without justification; this document is where that justification lives.

## Scope

**In scope:** languages, frameworks, datastores, infrastructure, AI providers,
and core tooling, each with alternatives considered and the cost of being wrong.

**Out of scope:** libraries chosen inside a module, which are implementation
decisions; and deployment topology, which is in
`docs/delivery/15-deployment-strategy.md`.

---

## Evaluation Criteria

Every decision below was assessed against, in order:

1. **Fit for the requirement** — does it satisfy the NFRs in `04`?
2. **Reversal cost** — how expensive is it to change later? This dominates.
   Cheap-to-reverse decisions get made quickly; expensive ones get scrutiny.
3. **Operational burden** — who runs it at 3am, and what does that cost?
4. **Hiring and ecosystem** — can we staff it and get answers?
5. **Total cost** — licence, infrastructure, and the engineering time it consumes.

**Explicit bias: boring, proven technology.** Our innovation budget is spent on
the Delivery Graph and the AI layer. Spending it on infrastructure novelty as
well would be spending a budget we do not have twice.

---

## Summary

| Layer | Choice | Reversal cost |
| --- | --- | --- |
| Backend language/framework | PHP 8.4 + Laravel | Very high |
| Frontend | TypeScript + React + Vite | High |
| AI orchestration | Python 3.12 + FastAPI | Medium |
| Primary datastore | PostgreSQL 16+ | Very high |
| Vector storage | pgvector, then dedicated store if needed | Low |
| Cache / locks / rate limits | Redis | Low |
| Queue | Redis-backed, then SQS at scale | Low |
| Object storage | S3-compatible | Low |
| Search | Postgres FTS → OpenSearch when justified | Medium |
| Compute | Containers on a managed orchestrator | Medium |
| IaC | Terraform | Medium |
| CI/CD | GitHub Actions | Low |
| Observability | OpenTelemetry + managed backend | Low |
| Primary model provider | Anthropic Claude, behind an abstraction | Low (by design) |

---

## Backend: PHP 8.4 + Laravel

**Decision:** Laravel is the framework for the Core API, workers, and realtime
gateway.

### Why

- **Existing organizational investment.** `metrial-auth` is an in-house Laravel
  enterprise identity platform covering SSO, SAML/OIDC, SCIM, WebAuthn, ABAC
  and adaptive MFA. Identity is the highest-risk, highest-effort subsystem in a
  multi-tenant SaaS. Reusing a mature implementation removes months from the
  critical path on precisely the component where a mistake is unrecoverable.
  This is the single strongest argument, and it is an argument about *this*
  organization rather than about PHP in the abstract.
- **Team fluency.** The fastest stack is the one the team is already expert in.
  Framework-learning time is time not spent on the Delivery Graph.
- **Genuine fit for the workload.** The core is request/response CRUD,
  transactional writes, and background jobs. Modern PHP with an opcode cache
  handles this comfortably at our targets; PHP 8.4 has typed properties,
  enums, readonly classes and generics-by-annotation sufficient for strict
  domain modelling.
- **Batteries included:** queues, scheduling, migrations, validation,
  authorization, broadcasting, and a first-class testing story — all
  first-party, all consistent, all maintained.
- **Ecosystem maturity** for the boring-but-essential: payments, storage
  drivers, SSO, PDF generation.

### Honest disadvantages

- **Not the AI ecosystem.** Agent frameworks, evaluation harnesses and
  retrieval tooling are Python-first. **Directly mitigated** by extracting AI
  orchestration to Python (D-30) — the weakness is answered architecturally.
- **Per-request process model.** Less suited to high-concurrency long-lived
  connections than Node or Go. Mitigated by keeping AI work async (P-9) and
  evaluating a dedicated runtime for the realtime gateway if connection counts
  demand it.
- **Reputation.** Some engineers dismiss PHP, which affects senior hiring at the
  margin. Mitigated by the quality of the codebase itself — strict types,
  static analysis at maximum level, clean architecture. Modern Laravel written
  well is not what the reputation refers to.
- **CPU-bound work** is not its strength. Not relevant: our heavy compute is
  inference, which happens elsewhere.

### Alternatives considered

| Alternative | Strengths | Why not chosen |
| --- | --- | --- |
| **Node.js + NestJS** | Shared language with frontend; strong async; large ecosystem | Would discard the `metrial-auth` investment and the team's fluency. TypeScript backend type safety is weaker at runtime boundaries than assumed. No decisive advantage for a CRUD-and-jobs core. |
| **Go** | Excellent concurrency and resource efficiency; strong for extraction later | Slowest to develop rich domain logic; smallest ecosystem for SaaS essentials; would require hiring. Best kept as an option for a specific extracted service under load. |
| **Python + Django/FastAPI** | Unifies with the AI stack | Django's ORM and app model fight Clean Architecture; async story is improving but uneven; would put all our eggs in one runtime and lose `metrial-auth`. |
| **Java/Kotlin + Spring** | Genuine enterprise pedigree; excellent tooling | Heaviest development velocity cost for a small team; operational complexity; hiring cost. Justified at a scale we are not at. |
| **.NET** | Strong performance and tooling | No team expertise; no existing investment. Would be a defensible greenfield choice for a different team. |

**Migration risk if wrong:** very high — a full backend rewrite. This is why the
decision rests on concrete present advantages (existing identity platform, team
fluency, workload fit) rather than on benchmarks.

**Business impact:** ships the fastest given who we actually are, and directs
the entire innovation budget at the differentiator.

---

## Frontend: TypeScript + React + Vite

**Decision:** React SPA in TypeScript, built with Vite.

### Why

- **The interface is genuinely application-like** — document editing, graph
  visualization, streaming AI output, real-time collaboration. This is an app,
  not a content site.
- **Ecosystem depth** where we need specialists: rich-text and document
  editing, graph/diagram rendering, data grids, virtualization. These are
  expensive to build and mature in React.
- **TypeScript in strict mode** is non-negotiable given how much structure
  flows through the UI; API types are generated from the OpenAPI contract so
  drift becomes a compile error rather than a production bug.
- **Hiring depth** is the largest of any frontend ecosystem.
- **Vite** for fast dev feedback (M-4) and a straightforward production build.

### Honest disadvantages

- **Client-side rendering** hurts initial load (P-4). Mitigated by code
  splitting, CDN delivery, and route-level lazy loading — and mostly irrelevant
  because the product is an authenticated tool, not a public site needing SEO
  or instant cold loads.
- **Ecosystem churn and decision fatigue** — routing, state, data fetching all
  require choices. Mitigated by deciding once, in the frontend conventions doc,
  and enforcing consistency.
- **Bundle size discipline** requires active budgets in CI, not good intentions.

### Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| **Next.js** | SSR/SSG and file-based routing add real value for public content sites; our product is authenticated with negligible SEO surface. Would add a Node hosting tier and framework coupling for benefits we cannot use. |
| **Vue / Nuxt** | Excellent DX and gentler learning curve, but a smaller specialist-component ecosystem (document editing, graph rendering) and a shallower hiring pool. |
| **Svelte / SvelteKit** | Best-in-class ergonomics and output size; ecosystem too thin for the specialist components we depend on; hiring pool small. |
| **Livewire / Inertia (server-driven)** | Genuinely tempting — one language, no API duplication, very fast for CRUD. Rejected because streaming AI output, graph interaction and collaborative editing demand rich client state, and server-driven UI fights that. |
| **Angular** | Comprehensive and opinionated; heaviest learning curve; ecosystem momentum is behind React. |

**Reversal cost:** high but bounded — the API contract stays stable, so a
frontend rewrite does not touch the backend.

---

## AI Orchestration: Python 3.12 + FastAPI

**Decision:** a separate Python service for all agent workflows, retrieval,
evaluation and model interaction.

### Why

- **The entire AI ecosystem is Python-first** — provider SDKs land here first
  and most completely; agent orchestration, evaluation harnesses, retrieval and
  document-processing libraries are mature here and thin or absent elsewhere.
- **FastAPI** gives async-native IO (correct for concurrent inference calls),
  automatic OpenAPI generation, and Pydantic validation — which doubles as the
  structured-output schema layer for model responses.
- **Evaluation tooling** for LLM output quality lives in Python. Since our
  quality gates depend on evals (G4), this is not optional.
- **Independent deploy cadence** — prompt and model changes ship without
  redeploying the platform.

### Honest disadvantages

- **A second language** means two toolchains, two dependency ecosystems, two
  security surfaces, and context-switching cost. This is the real price, and it
  is accepted because the alternative — reimplementing agent orchestration and
  evaluation in PHP — is far more expensive and permanently behind.
- **A network boundary** adds latency and a failure mode. Acceptable because
  this path is already asynchronous and measured in seconds.
- **Python performance** is irrelevant here; the workload is IO-bound on
  provider calls.

### Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| **AI logic inside Laravel** | One language, no network hop. Rejected: PHP provider SDKs lag, agent/eval tooling is largely absent, and long-running inference inside the core violates its scaling and failure isolation (A-5, P-9). |
| **Direct provider calls from workers, no orchestration service** | Simplest possible. Rejected: no central prompt registry, no model routing, no consistent guardrails, no evaluation harness, no cost control — every module would reinvent them divergently. |
| **Managed agent platform (vendor)** | Fast to start. Rejected: deep lock-in on the component that *is* the product, limited control over retrieval scoping (T-7 is a hard requirement), and unpredictable cost at scale. |
| **Node.js orchestration** | Shares TypeScript with the frontend. Rejected: the eval and retrieval ecosystem is meaningfully behind Python's. |

**Reversal cost:** medium — a single service behind a defined interface.
Deliberately the most replaceable significant component, because this is the
layer most likely to be rewritten as the field moves.

---

## Primary Datastore: PostgreSQL 16+

**Decision:** PostgreSQL as the single system of record.

### Why — and why this is the most consequential decision here

- **Row-Level Security.** Postgres can enforce tenant isolation *in the
  database*, so a query missing its tenant filter returns nothing rather than
  another tenant's data. This is defence in depth for T-1, the requirement whose
  failure is unrecoverable. It is the deciding factor and no other mainstream
  option matches it.
- **JSONB** for the schemaless portions of artifacts (varying document
  structures) with real indexing — structured where structure matters,
  flexible where it does not, in one store.
- **pgvector** for embeddings, so retrieval starts without operating a separate
  vector database, and — critically — vector search participates in the same
  tenant-scoped queries and the same transaction as everything else.
- **Recursive CTEs** for graph traversal (P-3). The Delivery Graph is a graph,
  but a modestly sized, tenant-partitioned one; Postgres handles it well within
  our depth limits.
- **Operational maturity:** partitioning, logical replication, point-in-time
  recovery, mature managed offerings on every cloud.
- **Correctness:** strong constraint support, real check constraints, proper
  transactional DDL.

### Honest disadvantages

- **Write scaling is vertical** until sharding. Acceptable to well beyond our
  Phase 4 targets; read replicas absorb read growth; the graph is naturally
  tenant-partitionable when sharding is eventually needed.
- **Deep graph traversal** is slower than a native graph database. Mitigated by
  depth limits (P-3), tenant scoping, and materialized closure tables if
  benchmarks demand it.
- **pgvector at very large scale** trails dedicated vector stores. Accepted
  deliberately — see below.
- **Connection limits** require a pooler at scale. Standard, well-understood.

### Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| **MySQL / MariaDB** | Excellent and familiar, but no RLS (removing the strongest isolation defence), weaker JSON indexing, no first-class vector extension, weaker CTE performance. The isolation argument alone is decisive. |
| **Neo4j or another graph DB** | Genuinely better at deep traversal. Rejected as *primary*: we would still need a relational store for everything else, so this means two systems of record, distributed transactions, and synchronization bugs — to optimize an operation we can already meet with Postgres. Revisit as a derived read model only if P-3 fails on real data. |
| **MongoDB** | Flexible documents. Rejected: weaker transactional guarantees for a domain that is heavily relational and approval-driven, no RLS equivalent, and traceability is inherently about relationships. |
| **DynamoDB / Cassandra** | Enormous scale, predictable latency. Rejected: our access patterns are exploratory and relational — exactly what these are worst at. Designing our domain around their key model would be tail wagging dog. |
| **Separate vector DB (Pinecone, Qdrant, Weaviate) from day one** | Better vector performance. Rejected *initially*: another system to operate and secure, and tenant-scoped retrieval (T-7) becomes an application-level guarantee instead of a database-level one. Migration path is straightforward when volume justifies it — a low-reversal-cost decision deliberately deferred. |

**Migration risk if wrong:** very high, hence the scrutiny. The mitigation is
that Postgres is the *least* likely of any choice here to be regretted at our
scale.

---

## Supporting Infrastructure

### Redis — cache, locks, rate limiting, ephemeral state

Single well-understood component covering several needs, with mature Laravel
integration. **Constraint: never a system of record.** Anything that must
survive a Redis failure lives in Postgres. This rule prevents the common
failure where a cache quietly becomes load-bearing.

*Alternatives:* Memcached (cache only, no locks or rate limiting); in-process
caching (breaks with horizontal scaling, S-8).

### Queue — Redis-backed initially, SQS at scale

Start with Redis: no new infrastructure, adequate durability for our volumes,
excellent local development. Move to SQS when durability guarantees or volume
justify it — an operational change behind an existing abstraction.

*Trade-off:* Redis queues are less durable than SQS. Accepted at Phase 1
volumes and mitigated by idempotency (D-37) and job retry semantics.

*Alternatives:* RabbitMQ (more capable routing than we need, more to operate);
Kafka (event streaming at a scale and complexity we do not have — genuinely
worth revisiting if event volume becomes the dominant load).

### Object storage — S3-compatible

Uploaded documents, generated exports, large artifact payloads. Keeps large
blobs out of Postgres — where they would bloat backups and slow restores (A-7).
S3-compatible rather than S3-specific, preserving cloud portability at
negligible cost.

### Search — Postgres full-text first, OpenSearch when justified

Postgres FTS satisfies P-8 at early volumes with zero additional
infrastructure. OpenSearch is introduced only when a measured requirement
demands it — relevance tuning, faceting, or volume. **Adding a search cluster in
Phase 1 would be textbook speculative generality**, and it also duplicates the
tenant-isolation problem into a second system.

### Compute — containers on a managed orchestrator

Containers for dev/prod parity; a *managed* orchestrator to avoid the control-
plane operations tax. At our team size, running Kubernetes ourselves would
consume an engineer's full attention for benefits we cannot yet use.

*Alternatives:* self-managed Kubernetes (ops burden unjustified until a
dedicated platform team exists); VMs with configuration management (worse
parity, slower deploys); serverless functions (cold starts conflict with P-1;
poor fit for long-running workers).

*Reversal:* medium — containers are portable; the orchestrator is replaceable
because we depend on the container contract, not on vendor-specific primitives.

### IaC — Terraform

All infrastructure declared in code, reviewed like application code. Broadest
provider coverage, largest ecosystem, cross-cloud. *Alternatives:* CloudFormation
(single-cloud), Pulumi (attractive general-purpose languages, smaller
ecosystem), manual configuration (rejected outright — unreproducible and
unauditable).

### CI/CD — GitHub Actions

Native to where the code lives, no extra system to operate, adequate for our
pipeline. Reversal cost is low; pipeline logic stays in scripts rather than
YAML wherever practical, so a move elsewhere is mechanical.

### Observability — OpenTelemetry + managed backend

Instrument with OpenTelemetry so the *backend* is replaceable; use a managed
backend so we do not operate a telemetry cluster. This is the pattern that
keeps a genuinely expensive lock-in decision cheap.

---

## AI Model Providers

**Decision:** Anthropic Claude as the default provider, accessed exclusively
through an internal abstraction that supports routing and fallback across
providers.

### Why

- **Quality on the tasks that matter** — long-context reasoning over
  requirements and architecture, structured output, and instruction adherence,
  which is what document synthesis demands.
- **Large context windows** suit our workload: substantial grounding context
  assembled from the Delivery Graph.
- **Strong tool-use and structured-output support**, so we validate against
  schemas rather than parsing prose (D-42).
- **Enterprise data commitments** supporting C-3 (tenant data excluded from
  training) — a sales blocker if unmet.

Default models: the current Claude generation, selected per task tier — a
frontier model (Opus class) for synthesis-heavy work such as architecture and
BRD generation, a balanced model (Sonnet class) for most generation, and a fast
model (Haiku class) for classification, extraction and routing. Concrete model
identifiers are configuration, not architecture, and live in the AI strategy.

### Why the abstraction is mandatory

Model capability, pricing and availability change on a timescale of months. Any
code that calls a provider SDK directly is code that will be rewritten. The
abstraction gives us:

- **Provider fallback** when a provider degrades (A-3, A-5)
- **Task-tier routing** for cost control ($-2)
- **Consistent guardrails and cost accounting** in one place
- **Evaluation across providers** with the same harness, so switching is an
  evidence-based decision rather than a rewrite

**Trade-off accepted:** an abstraction over a fast-moving interface risks
lowest-common-denominator design, losing provider-specific capabilities. This
is mitigated by keeping the abstraction thin — capability-based, with explicit
escape hatches for provider-specific features where they earn their keep — and
by not pretending providers are interchangeable when they are not.

*Alternatives:* single-provider lock-in (cheaper and simpler, unacceptable
concentration risk on the product's core capability); self-hosted open models
(full control and no per-token cost, but GPU operations and quality gaps we
cannot fund now — revisit for narrow high-volume tasks such as classification,
where the economics are strongest); a third-party gateway (convenient, adds a
dependency in the critical path and another processor of tenant data).

---

## Decisions

| ID | Decision | Reversal cost |
| --- | --- | --- |
| D-40 | PHP 8.4 + Laravel for the core | Very high |
| D-41 | TypeScript + React + Vite for the web app | High |
| D-42 | Python + FastAPI for AI orchestration | Medium |
| D-43 | PostgreSQL as the single system of record | Very high |
| D-44 | Postgres RLS as the enforced isolation boundary | High |
| D-45 | pgvector first; dedicated vector store only when measured need arises | Low |
| D-46 | Redis for cache/locks/limits, never a system of record | Low |
| D-47 | Postgres FTS first; OpenSearch only when justified | Medium |
| D-48 | Managed container orchestration, not self-managed Kubernetes | Medium |
| D-49 | Terraform for all infrastructure | Medium |
| D-50 | OpenTelemetry instrumentation with a managed backend | Low |
| D-51 | Claude as default model, always behind a provider abstraction | Low by design |
| D-52 | Structured output validated against schemas; never prose parsing | Low |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| PHP limits senior hiring | Slower team growth | Codebase quality as the recruiting argument; Python and TypeScript roles broaden the funnel |
| ~~`metrial-auth` proves incompatible with our tenancy model~~ — **resolved**: found unreachable, not incompatible; the strongest reuse argument for Laravel no longer holds in practice (see ADR-0001) | Realized — Identity is now built in-house | N/A |
| Two backend languages fragment the team | Context-switching cost, uneven quality | Clear ownership boundaries; shared standards; the Python surface stays deliberately small |
| pgvector outgrown sooner than expected | Retrieval performance degrades | Retrieval sits behind an interface; migration is planned, low-cost, and pre-considered |
| Postgres write throughput becomes the ceiling | Forced sharding under pressure | Tenant-partitionable design from day one; read replicas first; monitor headroom against S-4 |
| Model provider pricing or availability shifts | Margin or availability impact | Provider abstraction, task-tier routing, evaluated fallbacks |
| Provider abstraction becomes lowest-common-denominator | Loss of capability | Thin, capability-based abstraction with explicit escape hatches |

## Dependencies

- **Depends on:** NFRs (`04`), architecture (`05`).
- **Depended on by:** multi-tenancy (RLS), scalability, security, AI strategy,
  coding standards, testing, deployment.

## Future Improvements

- ~~Complete the `metrial-auth` evaluation spike and record the outcome as
  ADR-0001 before any identity code is written.~~ Done —
  `docs/architecture/adr/0001-metrial-auth-evaluation-outcome.md`: unreachable,
  Identity built in-house.
- Benchmark graph traversal on realistic volumes to confirm Postgres meets P-3.
- Re-evaluate self-hosted models for high-volume classification once usage
  patterns are known.
- Reassess the realtime gateway runtime if concurrent connections approach the
  per-instance limits of the PHP process model.
