# ADR-0002: Genuine Multi-Provider AI Support

- **Status:** Accepted
- **Date:** 2026-08-07
- **Deciders:** Engineering leadership
- **Supersedes:** none — amends the intent of D-51
- **Related:** D-51, D-550 through D-562, `docs/architecture/06-technology-decisions.md`, `docs/architecture/ai/50-ai-platform-and-multi-provider.md`

## Context

D-51 established Claude as the default model, "always behind a provider
abstraction." At the time that decision was recorded, the abstraction was a
design commitment, not a proven capability — no second provider adapter existed,
and the abstraction had never carried real traffic for any model besides
Claude.

Three things changed the calculus enough to warrant revisiting the decision
rather than merely implementing it as originally scoped:

1. **Concentration risk on the core capability.** The AI layer is the platform's
   differentiator (`docs/product/01-vision-mission-and-goals.md`). A
   single-provider architecture ties the entire product's availability,
   pricing and capability trajectory to one vendor's roadmap. `19` (risk
   register) rates provider dependency (TR-4) as Medium likelihood; a
   single-provider posture converts its impact from Medium to High, because
   there is no fallback that has ever been exercised.
2. **Per-task capability divergence is now material.** Long-context synthesis,
   structured-output fidelity, reasoning depth and price-per-token differ
   enough across frontier models that the best model for BRD synthesis is
   demonstrably not the best model for classification. An abstraction that
   only ever serves one provider cannot capture this even if it is
   architecturally capable of it.
3. **Enterprise tenant governance requirements will arrive.** `19` (BR-5) and
   `docs/architecture/data/48-gdpr-and-data-protection.md` anticipate tenants
   who contractually restrict which processors may see their data. A
   single-provider platform cannot satisfy a tenant who excludes that
   provider, full stop — there is no degraded mode, only "we cannot serve
   this customer."

**What is not known:** actual comparative evaluation scores between Claude, GPT,
Gemini and DeepSeek on our specific workflows do not exist yet — no golden sets
have been run, because no workflows have been implemented. This ADR authorizes
the *architecture* that makes such evaluation possible and mandatory before any
non-Claude provider is promoted to `active` for a given workflow
(`docs/architecture/ai/57-evaluation-system.md`, D-646). It does not itself
claim any provider is better than another for any task — that claim will be
made, per workflow, by evaluation runs after implementation begins.

## Decision

Amend D-51: the platform supports Anthropic Claude, OpenAI GPT, Google Gemini,
DeepSeek and future providers as first-class, independently routable options.
Every model call passes through a single AI Gateway. Workflows declare required
*capabilities*, never a specific model. Model selection is capability-filtered,
then governance-filtered per tenant, then ranked by evaluated quality and cost
for that specific (workflow, model) pairing. No provider is default in the sense
D-51 originally meant; Claude remains the best-evaluated choice for most
workflows at the time of writing, which is an empirical starting position, not
an architectural default.

## Reasoning

**Why:** the three drivers in Context are structural, not speculative — they
describe properties of the business (differentiator concentration, tenant
governance) and the market (capability divergence) that will not go away.
Building genuine multi-provider support once, correctly, is cheaper than
retrofitting it after a single-provider architecture has calcified around
Claude-specific assumptions in prompts, error handling and cost models.

**Why not the alternatives:** see the table below. The short version is that
every alternative either accepts the concentration risk, forfeits per-task
optimization, or cannot satisfy governance-constrained tenants — and no
alternative is meaningfully cheaper to build than genuine support, because most
of the cost is in the abstraction layer itself, which any credible fallback
story requires regardless.

**Future scalability:** the model registry (`50`) and capability-matching design
mean adding a fifth, sixth or Nth provider is an adapter plus a conformance-suite
pass (`50`, "Adding a Provider"), not an architectural change. This is the
property that makes the decision durable against a model landscape expected to
keep changing on a 1–3 year cycle (`docs/foundation/23-architecture-philosophy.md`).

**Maintenance cost:** real and stated plainly in `50` — N adapters to build and
track independently, normalization that is lossy in both directions, and
evaluation cost that multiplies by candidate provider. This is the primary cost
of the decision and is accepted deliberately rather than discovered later.

**Operational cost:** each active provider adds a rate-limit domain, a circuit
breaker, and a data-governance entry in the sub-processor register (`48`). No
new infrastructure class — the gateway, cache and queue architecture (`37`, `51`)
are provider-count-agnostic by design.

**Migration risk:** low to reverse partially — a single underperforming provider
can be demoted to `trial` or removed from a workflow's candidate set without
touching the gateway or any workflow definition, because workflows never name a
model (D-554). Reversing the *architecture* back to single-provider would be
expensive, but nothing in the roadmap suggests that reversal will ever be
wanted; the risk asymmetry favors having built this.

**Business impact:** removes the single largest unmitigated dependency risk on
the product's core capability, enables price-leverage cost optimization ($-1
through $-4), and is a precondition for closing enterprise deals with
processor-restriction clauses (`19` BR-5).

## Alternatives considered

| Option | Strengths | Why not chosen |
| --- | --- | --- |
| Single provider (Claude only) | Simplest to build and reason about; best possible use of Claude-specific features without abstraction overhead | Unacceptable concentration risk on the differentiator; no price leverage; cannot serve processor-restricted enterprise tenants at all |
| Two providers, primary and fallback only | Most of the resilience benefit at lower build cost than full multi-provider | The fallback is realistically unevaluated for most workflows, making it an untested behavior change that activates precisely during an incident (D-440); forfeits per-task quality optimization |
| Third-party AI gateway product | Fast to adopt; normalization and routing built in | Inserts a dependency in the path of the core capability; adds an undisclosed processor of tenant content; surrenders routing and caching policy — direct contradiction of D-238 |
| Full multi-provider, in-house gateway (chosen) | No single-provider dependency; per-task quality and cost optimization backed by evidence; satisfies tenant governance; capacity headroom across independent rate limits | Highest build and maintenance cost; accepted deliberately given the stakes |

## Consequences

**Positive:** the platform's availability and quality no longer depend on one
vendor's decisions. Cost optimization becomes possible on measured
quality-per-dollar rather than a single price list. Enterprise deals requiring
processor restrictions become servable rather than automatically lost.

**Negative:** engineering effort is now split across N adapters instead of one
integration; evaluation cost multiplies by candidate provider count (`57`);
cognitive load on engineers reasoning about model behavior increases, since more
than one model's quirks are now in scope. Normalization is lossy — some
provider-specific capability is only reachable through declared escape hatches
that carry a recorded portability cost (D-558), which is a permanent tax on
using anything provider-specific.

**Neutral:** Claude remains the best-evaluated default for most workflows at
launch; this ADR changes the architecture's capability, not necessarily
tomorrow's routing table.

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Adapter maintenance burden exceeds team capacity as providers update their APIs | Adapters drift; failover paths silently rot | Conformance suite runs continuously (D-561); a failing adapter is automatically demoted to `trial` |
| Normalization hides a semantic difference between providers | Subtle cross-provider quality or correctness divergence | Conformance suite tests semantics, not only request/response shape; cross-provider evaluation isolates adapter defects from genuine capability gaps (D-647) |
| Evaluation cost becomes a reason to skip cross-provider validation under deadline pressure | A provider is promoted on assumption rather than evidence | Promotion to `active` for a workflow structurally requires a full golden-set run on that (workflow, model) pair (D-646) — there is no code path that promotes without it |
| Tenant governance filter is bypassed by a routing code path added outside the gateway | Contractual breach of a processor-restriction clause | Enforced structurally at gateway stage 2, before any optimization; direct adapter calls are prohibited by import restriction (D-551, D-563) |

## Dependencies

Requires the AI Gateway (`51`), the model registry and capability model (`50`),
and the evaluation system (`57`) to exist before any provider beyond the first
is promoted to `active` for a workflow. Depended on by the routing, caching and
cost-optimization mechanics throughout `50`–`58`, and by the sub-processor
register in `48`.

## Future Improvements

- Record actual per-workflow evaluation outcomes here or in a follow-up ADR once
  Phase 1 workflows exist and golden-set runs against multiple providers have
  occurred — this ADR authorizes the architecture, not a specific routing table.
- Revisit the adapter maintenance cost assumption after the second and third
  adapters are built, to calibrate whether the stated cost was accurate.
- Add self-hosted open-weight models as a registry provider once volume
  justifies GPU operations (`docs/foundation/24-technology-selection-strategy.md`),
  as a further instance of this same architecture rather than a new decision.
