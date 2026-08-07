# ADR-0003: Bounded Agent Steps Within Workflows

- **Status:** Accepted
- **Date:** 2026-08-07
- **Deciders:** Engineering leadership
- **Supersedes:** none — amends the intent of D-89
- **Related:** D-89, D-628 through D-637, `docs/architecture/10-ai-strategy.md`, `docs/architecture/ai/56-workflow-and-agent-engine.md`

## Context

D-89 established that platform AI capabilities are defined workflows, never
open-ended chat or free-form agents, "because only workflows are evaluable,
cacheable, cost-routable and graph-linkable." That reasoning targeted a specific
failure mode: an agent with unrestricted tool access and unbounded looping is
unevaluable (no defined correct output to grade), unbounded in cost (nothing
stops it calling tools indefinitely), and unbounded in scope (nothing constrains
what it can read, write or invoke).

That objection remains fully valid and is not being reconsidered. What has
changed is the identification of a narrower case the original decision did not
distinguish: a step with a *declared goal*, a *fixed tool allowlist*, an
*engine-enforced iteration ceiling*, and a *mandatory schema-validated output*
is evaluable (the golden set grades the outcome), cost-bounded (ceiling × per-
call cost, checked before every call), and scope-bounded (by the allowlist,
checked before every call). The original objection was to *unboundedness*; it
does not transfer to a case built specifically to remove that property.

Concretely, several genuinely useful capabilities are poorly served by a rigid
deterministic pipeline: root-causing why an estimate diverged from historical
actuals, exploring which prior architecture pattern best fits a new
requirement, or iteratively refining a retrieval query when the first pass
returns insufficient grounding. Forcing these into a fixed step sequence
produces worse output than acknowledging the task is exploratory and giving it
a mechanism suited to that — while keeping every guarantee D-89 was protecting.

**What is not known:** no agent step has been implemented or evaluated yet.
This ADR authorizes the *mechanism* — the bounded agent step type, its engine-
enforced loop, and its tool registry — not any specific agent step's design or
its production performance. Whether bounded agents actually outperform
deterministic pipelines on the exploratory tasks named above is an empirical
question the evaluation system (`57`) will answer per step, after
implementation.

## Decision

Amend D-89: workflows remain the sole unit of capability — this is unchanged.
An `agent` step type is added as one of the closed set of step types a workflow
may compose. Within an agent step, a model may reason across multiple tool
calls toward a declared goal, but the *engine* — not the model — enforces a
fixed tool allowlist, a hard iteration ceiling, and an independent cost ceiling
before permitting every tool call. No tool available to an agent step executes
a domain state change directly; any proposed write surfaces to the platform's
existing draft/approval boundary (D-36) rather than executing. There is no
agent-to-agent negotiation; multi-step reasoning is coordinated only through the
deterministic workflow graph, with each agent step's output a typed,
schema-validated contract like any other step boundary.

## Reasoning

**Why:** the containment properties D-89 required — evaluability, cost bounds,
scope bounds — are fully preserved by construction. The engine, not the model,
is the enforcement point for every bound, which means the containment holds
regardless of how convincingly a compromised or confused model reasons about
extending its own scope (`docs/architecture/ai/58-ai-and-prompt-security.md`
makes this the primary injection-containment argument for this step type: even
a successful prompt injection inside an agent step can at most attempt a tool
call within the allowlist, and can never execute a write directly).

**Why not the alternatives:** see the table below. The essential distinction is
between *unbounded* agency, which D-89 correctly rejected and which none of the
alternatives besides the chosen one avoid, and *bounded* agency, which is a
different risk profile entirely.

**Future scalability:** the tool registry (`56`) grows by adding typed,
individually authorized tools — each addition is reviewed for its capability
class and side-effect profile, so the scope of what agent steps can reach grows
under the same governance as everything else, rather than by relaxing a global
setting.

**Maintenance cost:** agent steps are harder to evaluate than deterministic
steps because the *path* to an outcome varies even when the outcome is graded
consistently (`57`). This is a real, ongoing cost, met by weighting human
sampling higher for agent-step evaluation than for deterministic generation
(D-649) and by monitoring termination-reason distribution continuously as a
leading indicator of a mis-scoped goal (D-650, `54`).

**Operational cost:** every agent step logs its full tool-call and reasoning
trace (D-637), which is additional telemetry volume beyond `38`'s standard
requirements — accepted because reconstructing an agent step's path after the
fact is what makes its output explicable in the same sense every other
generation is required to be (U-4).

**Migration risk:** low. The agent step type is additive to the closed set of
step types; removing it, or declining to use it for a given workflow, requires
no change to any other step type or to the workflow engine's execution model.

**Business impact:** enables exploratory-reasoning capabilities (root-cause
analysis on estimation variance, architecture-pattern matching) that a rigid
pipeline handles poorly, without reopening the risk that motivated D-89's
original prohibition.

## Alternatives considered

| Option | Strengths | Why not chosen |
| --- | --- | --- |
| No agents, ever (status quo, D-89 unchanged) | Safest; zero new attack surface or evaluation complexity | Forecloses genuinely better solutions for exploratory tasks; conflates "unbounded" with "agentic," which are not the same property |
| Free-form agent framework, unrestricted tool use and looping | Maximum capability and flexibility | Exactly what D-89 rejected: unevaluable, unbounded cost, unbounded scope — the objection this ADR does not reopen |
| Bounded agent step within workflows (chosen) | Preserves evaluability, cost bounds and scope bounds while enabling exploratory reasoning; engine-enforced containment holds regardless of model behavior | Harder to evaluate than deterministic steps; additional telemetry and review burden per agent step |

## Consequences

**Positive:** exploratory tasks gain a mechanism suited to them. The
containment properties that made workflows safe — evaluability, cost bounds,
auditability, no unreviewed state changes — are preserved by construction
rather than by policy, meaning they hold even under adversarial input
(prompt injection) because the engine, not the model, is the enforcement
point.

**Negative:** agent steps are genuinely harder to evaluate, debug and reason
about than deterministic steps. Evaluation cost and human-sampling weight are
both higher for agent steps (D-649). A mis-scoped goal or an overly narrow tool
allowlist produces frequent clean failures (iteration-ceiling terminations)
rather than a useful result, which is a worse user experience than a
deterministic step's predictable behavior until the step is tuned.

**Neutral:** the number of workflows using agent steps at launch is expected to
be small — this ADR authorizes the mechanism, not a mandate to convert existing
deterministic workflows to agentic ones.

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Tool allowlist scoped too broadly for a given agent step, out of convenience | Reintroduces the unbounded-scope risk D-89 identified, for that step specifically | Reviewed per workflow in the standard AI-workflow review checklist (`docs/delivery/16-review-process-and-quality-gates.md`); write and irreversible-side-effect tools require explicit justification |
| Iteration ceiling set too low for genuinely complex goals, or too high for cost discipline | Frequent unproductive clean failures, or unnecessary spend | Ceiling tuned from observed termination-reason distribution (`54`, D-650); a rising iteration-ceiling termination rate triggers step redesign, not a raised ceiling, as the default response |
| Multi-step-shaped workflows recreate agent-to-agent negotiation informally through chained agent steps | The unevaluability and unauditability problem D-89 targeted returns by a different path | Each step's output remains a typed, schema-validated contract regardless of step type; review checks for agent steps whose real function is passing unstructured control to the next rather than adding bounded reasoning |
| Agent step trace volume overwhelms tracing infrastructure or budget | Observability cost, or reduced trace retention undermining explicability | Sampling policy mirrors the 100%-retention treatment already given to AI generation traces (`38`); tiered rather than unlimited |

## Dependencies

Requires the Workflow and Agent Engine (`56`) and its tool registry, the
evaluation system's agent-step extensions (`57`), and the AI security
containment argument (`58`) to all exist together — none of the three is
sufficient alone to make bounded agency safe. Depended on by any workflow that
declares an `agent` step.

## Future Improvements

- Record observed termination-reason distributions and evaluation results here
  or in a follow-up note once the first agent steps are implemented and have
  run in production — this ADR authorizes the mechanism ahead of that evidence.
- Revisit whether a dedicated authoring pattern for termination-criteria design
  is needed once several agent steps exist to compare, per `56`'s stated future
  improvement.
- Extend the adversarial evaluation suite with agent-specific attack patterns
  targeting tool-allowlist evasion once the first agent steps exist to test
  against (`58`'s stated future improvement).
