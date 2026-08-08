# AI Orchestration

The AI Orchestration context (`docs/product/03-core-modules-and-scope.md`,
C11) — "deliberately a context, not a library. Every module needs AI; if
each implements its own prompting, we get eleven divergent implementations,
no central cost control, no consistent guardrails, and no way to evaluate
quality." This module owns the Model Registry, the governance/capability
filter stage of routing, and the P-9 queued-generation boundary. It does
not yet own prompt composition, evaluation, guardrails, or any live
provider call — see Scope below.

## Governance already satisfied

Two decisions this module implements each required an ADR before
implementation (`docs/architecture/ai/50-ai-platform-and-multi-provider.md`):
genuine multi-provider support (amends D-51) and bounded agent steps
within workflows (amends D-89). Both ADRs — `docs/architecture/adr/0002-genuine-multi-provider-ai-support.md`
and `docs/architecture/adr/0003-bounded-agent-steps-within-workflows.md` —
already existed, `Accepted`, before this module was built (they were
extracted from the fully-argued decisions already in docs `50` and `56`,
per the ADR README). No new ADR was needed for this pass.

## Scope

**In:** the Model Registry (`ModelRegistryEntry`: pinned model id,
provider, tier, capabilities, context window, pricing, status —
Trial/Active/Deprecated/Retired); per-tenant provider governance
(`TenantProviderPolicy`, D-559); the candidate-filter portion of routing
(`SelectCandidateModels`: governance, then status, then capability and
context-window match — D-566's filter stage, not its rank stage); the P-9
sync/async boundary (`RequestGeneration` queues a `GenerationRecord` and
returns immediately; `ProcessGenerationRequest` — a queued job — advances
it to `Selected` or `Failed`).

**Out, deliberately, and why each is a real gap rather than an oversight:**

- **No live provider adapter.** No `ANTHROPIC_API_KEY` or equivalent
  exists in this environment (checked `.env`/`.env.example` before
  starting). `ModelAdapter` is defined as a contract; `NullModelAdapter` is
  bound by default and throws `ProviderNotConfigured` on
  `dispatch()` — the honest behavior for "no real adapter exists," not a
  faked response. `ProcessGenerationRequest` never calls `dispatch()` in
  this pass; it stops at candidate selection.
- **No ranking.** D-566's rank stage (workflow pin, evaluation score,
  cost, latency, load) needs the evaluation system (`57`) and real
  workflows to produce evidence. `SelectCandidateModels` returns filtered
  candidates in registry order — not a preference order.
- **No budget check, cache, rate limiting, circuit breaker, guardrails, or
  cost accounting.** Each is a real subsystem the full gateway pipeline
  (`docs/architecture/ai/51-ai-gateway.md`) specifies, and each needs
  something this codebase doesn't have yet: budget needs a usage-limit
  model (Billing is Phase 2); cache needs real calls to be worth keying;
  guardrail content is its own document (`58`); cost accounting needs a
  real completed call to account for.
- **No public write path for the registry or tenant policies.**
  `RegisterModel` and `SetTenantProviderPolicy` exist as Application-layer
  use cases, invoked from tests and (in production) a seeder — never from
  an HTTP controller. The registry is "configuration, reviewed like code,
  not runtime-editable" (D-280); tenant provider policy needs a real admin
  surface (Platform Administration, C15), not yet built.
- **`GenerationRecord` carries no cost or lineage.** D-469 describes
  `GenerationRecord` as "the source of truth for AI cost and lineage" —
  this pass's version only carries candidate selection, because nothing
  dispatches to a real provider to produce cost or lineage data yet.

## Layout

Standard module layering (`docs/delivery/11-repository-and-folder-strategy.md`):

```
Domain/           Provider/ModelTier/ModelStatus/GenerationStatus enums;
                  ModelCapabilities + CapabilityRequirement (with the
                  satisfies() filter predicate) and ModelPricing value
                  objects; ModelRegistryEntry, TenantProviderPolicy and
                  GenerationRecord aggregates; ModelAdapter and
                  GenerationDispatcher as ports (interfaces) -- the latter
                  exists so the Application layer depends on an
                  abstraction rather than reaching into Infrastructure's
                  concrete queue Job class, the same discipline
                  repositories follow elsewhere in this codebase.
                  Framework-free (D-130).
Application/      RegisterModel, SetTenantProviderPolicy (seeder-only, no
                  HTTP route), SelectCandidateModels (the filter stage),
                  RequestGeneration (the P-9 boundary).
Infrastructure/   Eloquent models and repositories, NullModelAdapter,
                  QueuedGenerationDispatcher, ProcessGenerationRequest (a
                  queued Job -- a framework-level entry point into
                  Application logic, the same role a Controller plays for
                  HTTP), the service provider.
Presentation/     RequestGenerationController (POST, 202 Accepted -- the
                  first genuinely asynchronous endpoint in this codebase;
                  the response body reports `queued`, not a result, by
                  design), GetGenerationRequestController (poll for
                  status).
```

## The Model Registry has no `tenant_id`

Deliberately: it is platform-wide configuration, not tenant data, so
there is nothing to isolate between tenants and no RLS policy on
`model_registry_entries` — every tenant reads the same registry, filtered
only by their own `TenantProviderPolicy` at the Application layer. This is
asserted directly by a test (`tests/Isolation/AiOrchestrationIsolationTest.php`),
not left as an unstated assumption the way an absent RLS policy usually
would be.
