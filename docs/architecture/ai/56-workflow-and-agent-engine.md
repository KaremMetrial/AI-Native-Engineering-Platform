# Workflow and Agent Engine

## Purpose

Specify the execution engine for AI capabilities: the declarative workflow model
from `40`, deepened, and the bounded agent step type this document introduces as
the amendment to D-89.

## Scope

**In scope:** the workflow execution model, step types including the bounded
agent step, tool registry and authorization, planning and reflection patterns,
multi-agent coordination, and execution bounds.

**Out of scope:** provider routing (`50`, `51`), prompt composition (`52`),
evaluation of workflow output (`57`).

---

## Amendment to D-89: Bounded Agents

**Decision.** Workflows remain the unit of capability (D-89 stands). An
**`agent` step type** is added: within a single step, a model may reason across
multiple tool calls toward a declared goal, inside hard bounds enforced by the
engine, not by the model.

**Reasoning.** D-89 rejected "an agent with free tool use and open-ended looping"
because it is unevaluable, unbounded in cost, and unbounded in scope. That
objection is correct and remains correct for the *unbounded* case. It does not
apply to a *bounded* case: a step with a declared goal, a fixed tool allowlist,
a hard iteration ceiling, and mandatory schema-validated output is evaluable (the
golden set targets the step's outcome), cost-bounded (bounded by iteration
ceiling × per-call cost), and scope-bounded (by the tool allowlist).

Some tasks are genuinely better served by agentic reasoning than by a fixed
pipeline — root-causing why an estimate diverged from history, exploring which
prior architecture pattern best fits a new requirement, iteratively refining
retrieval when the first pass is insufficient. Forcing these into a rigid
pipeline produces worse output than the pipeline pretending certainty it does
not have. The fix is bounding agent behavior, not prohibiting it.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| **No agents, ever** (status quo) | Safest; forecloses genuinely better solutions for exploratory tasks, and the objection was to *unboundedness*, not to agency itself |
| **Free-form agent framework** | Maximum capability; unevaluable, unbounded cost, unbounded scope — exactly the original objection |
| **Bounded agent as a step type** *(chosen)* | Preserves evaluability, cost bounds and scope bounds while enabling exploratory reasoning where it earns its place |

**Trade-offs.** Agent steps are harder to evaluate than deterministic steps — the
*path* varies even when the *outcome* is graded, so evaluation targets outcome
and tool-use appropriateness rather than an exact trace. They are also harder to
debug: an unexpected result requires reconstructing which tool calls led there,
which is why every agent step logs its full trace (below).

**Benefits.** Exploratory tasks get a mechanism suited to them, without
reintroducing the risks D-89 correctly identified.

**Long-term impact.** This keeps the platform able to use agentic capability as
it matures, while keeping the guarantee that matters — every AI action is
bounded, evaluable and auditable — intact regardless of step type.

**This amendment requires ADR-0003 before implementation**, per `50`.

---

## Workflow Execution Model

Extended from `40`. A workflow is a declarative step graph; steps are typed.

```
   WorkflowDefinition
   ├── metadata           id, version, owner
   ├── inputs, outputs    typed contracts
   ├── bounds             max_steps · max_wall_clock · max_total_cost
   └── steps[]
       ├── DeterministicStep    validation, transformation — no inference
       ├── GenerationStep       single prompt → schema-validated output
       ├── RetrievalStep        context assembly — no inference (`55`)
       ├── AgentStep             ← NEW: bounded multi-turn reasoning
       ├── FanOutStep            parallel generation steps
       └── DecisionStep          deterministic branch on prior output
```

**Step types are closed**, like link types (D-338) and audit actions (D-519): an
open set of step behaviours would make workflow analysis — cost estimation,
evaluation design, security review — unable to reason about what a workflow can
possibly do.

### Execution properties (restated from `40`, unchanged)

State persisted after every step (D-322); resumable on failure; no cycles between
steps (D-437); bounds enforced by the engine, not by convention.

---

## The Agent Step

```
   AgentStep
   ├── goal                 explicit, structured — not "figure it out"
   ├── tool_allowlist        [ ] explicit — no default tool access
   ├── max_iterations        hard ceiling
   ├── max_cost               hard ceiling, independent of platform budget
   ├── output_contract        schema — same discipline as GenerationStep
   ├── termination_criteria   goal-met test, separate from the iteration cap
   └── trust_context          which knowledge tiers and memory it may consult
```

```
   ┌─────────────────────────────────────────────────────┐
   │  AGENT LOOP  (engine-controlled, not model-controlled)│
   │                                                        │
   │   observe → reason → select tool → call → observe     │
   │       │                                    │            │
   │       └──── check termination criteria ────┘            │
   │                    │                                    │
   │         met?  ──── yes ──▶ emit result                  │
   │              │                                          │
   │              no, and iteration < max ──▶ loop            │
   │              │                                          │
   │              no, and iteration = max ──▶ CLEAN FAILURE   │
   │                                          (D-95's rule)   │
   └─────────────────────────────────────────────────────┘
```

**The engine controls the loop, not the model.** The model proposes the next
tool call; the engine decides whether to permit it — checking the allowlist,
the iteration count, the cost ceiling and the trust context before every call.
A model cannot extend its own budget or reach outside its declared tools by
constructing a clever request, because the check happens outside anything the
model influences (D-434's principle, applied to execution rather than only to
retrieval scope).

**Hitting the iteration ceiling is a clean failure, not a partial result.**
Consistent with D-95: an agent step that ran out of budget without meeting its
termination criteria has not produced a usable answer, and returning its
partial reasoning as though it were one would be exactly the coerced-output
failure D-95 exists to prevent.

### Tool registry

**Decision.** Tools are centrally registered, typed, and individually
authorized per workflow. There is no ambient tool access.

| Tool property | Purpose |
| --- | --- |
| `name`, `schema` | Typed input/output, like any API |
| `capability_class` | read_graph · read_knowledge · compute · **write** |
| `tenant_scoping` | **Mandatory and structural** — a tool cannot query outside its caller's tenant |
| `cost` | Contributes to the step's cost ceiling |
| `side_effects` | none · reversible · irreversible |

**Write and irreversible tools require explicit, per-workflow authorization and
are rare.** The overwhelming majority of agent steps need only read tools —
traverse the graph, query knowledge, run a calculation. A step is granted the
minimum tool set its goal requires, following D-434's minimization principle
extended from guardrails to execution generally.

**No tool executes application state changes directly.** A tool that would
modify domain state instead proposes a change, which surfaces to the workflow's
existing draft/approval boundary (D-36) — an agent step can *suggest* that a
requirement be marked complete; it cannot mark it complete. This is what keeps
D-36 intact under agentic execution: bounding the loop is necessary but not
sufficient, and this is the second half of the containment.

### Planning and reflection

Two patterns available within an agent step's reasoning, both still inside the
same bounds:

| Pattern | Use | Bound |
| --- | --- | --- |
| **Plan-then-execute** | Goal decomposes into a foreseeable sequence | Plan itself counts against `max_iterations` |
| **Reflect-and-retry** | Output can be self-checked against the contract | Reflection passes count against the same ceiling — not a separate budget |

**Reflection does not get its own budget.** A step permitted unlimited
self-correction attempts inside a nominally bounded ceiling is not actually
bounded; the ceiling would be theatre. Every iteration — reasoning, tool call,
or self-check — draws from the same counter.

---

## Multi-Agent Coordination

**Decision.** Multiple agent steps may run within one workflow, coordinated by
the deterministic workflow graph. There is no agent-to-agent negotiation and no
emergent multi-agent behaviour.

**Reasoning.** Frameworks where agents communicate with each other and
negotiate toward a goal are expressive and are unevaluable and unauditable in
exactly the way D-89 rejected — the emergent path between agents is not
something a golden set can meaningfully grade, and the audit trail becomes a
conversation transcript rather than a decision record. A workflow with several
agent steps, each bounded and each feeding the next through the deterministic
graph, achieves most of the same decomposition with a fully traceable structure.

**Pattern:**

```
   AgentStep(explore_prior_architectures)
        │  structured output — a ranked shortlist, not prose
        ▼
   DecisionStep(select approach, deterministic rule on the shortlist)
        │
        ▼
   AgentStep(draft architecture for the selected approach)
        │
        ▼
   GenerationStep(format as the final document)
```

Each agent step's output is a typed, schema-validated contract that becomes the
next step's typed input — the same discipline as any other step boundary. What
looks like "agents talking to each other" is, structurally, one agent producing
output that a deterministic step routes to the next.

---

## Execution Bounds

Consolidated. Every bound is enforced by the engine and is a hard ceiling, not
a target.

| Bound | Scope | Enforced by |
| --- | --- | --- |
| `max_steps` | Whole workflow | Workflow Engine |
| `max_wall_clock` | Whole workflow | Workflow Engine |
| `max_total_cost` | Whole workflow | Workflow Engine, checked before each step dispatches |
| `max_iterations` | Per agent step | Agent loop controller |
| `max_cost` | Per agent step | Agent loop controller, independent of the platform-wide budget hierarchy (`54`) |
| Tool allowlist | Per agent step | Tool registry, checked per call |
| No cycles | Structural | Rejected at workflow definition time (D-437) |

**Two independent cost ceilings compose deliberately** — the step's own
`max_cost` and the platform budget hierarchy from `54`. Either alone is
insufficient: a step-level ceiling without a platform ceiling permits many
expensive steps to add up; a platform ceiling without a step-level one lets one
runaway step consume an entire tenant's monthly budget before the platform
ceiling even notices.

---

## Observability for Agent Steps

Agent steps get additional tracing beyond `38`'s standard requirements, because
their execution path is genuinely variable and that variability is exactly what
needs to be reconstructable after the fact.

| Recorded | Purpose |
| --- | --- |
| Every tool call and its result | Full reconstruction of the path taken |
| Every reasoning turn (as a span, D-414's link pattern) | Understanding why a tool was selected |
| Termination reason | Goal met vs. iteration ceiling vs. cost ceiling |
| Cost per iteration | Attributes spend within the step, not just to it |

This is what makes an agent step's output explicable in the same sense as any
other generation (U-4) — the reasoning path is recorded, not merely the final
answer.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-628 | **Amends D-89** — bounded agent steps added within workflows; free-form agents remain rejected | The original objection was to unboundedness, not to agency; bounding preserves evaluability, cost and scope |
| D-629 | Step types are a closed set | An open set would make workflow cost, security and evaluation analysis impossible |
| D-630 | The engine controls the agent loop; the model proposes, the engine permits | A model must not be able to extend its own budget or reach outside declared tools |
| D-631 | An exhausted iteration ceiling is a clean failure, not a partial result | Consistent with D-95 — a partial reasoning trace is not a usable answer |
| D-632 | Tools are centrally registered, typed and individually authorized; no ambient access | The minimum tool set for the goal is the default, not the maximum available |
| D-633 | No tool executes state changes directly; write actions surface through the existing approval boundary | Keeps D-36 intact under agentic execution — bounding the loop alone is not sufficient |
| D-634 | Reflection and self-correction draw from the same iteration ceiling, never a separate budget | A theoretically bounded step with unlimited self-correction is not actually bounded |
| D-635 | No agent-to-agent negotiation; coordination is via the deterministic workflow graph | Emergent multi-agent paths are unevaluable and unauditable — the exact original objection |
| D-636 | Two independent cost ceilings — step-level and platform-level — both apply | Either alone permits a failure mode the other closes |
| D-637 | Every tool call, reasoning turn and termination reason is recorded | Required to explain agent output years later, same as any other generation |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Agent step evaluation is harder than deterministic steps | Quality regressions less visible | Evaluation targets outcome and tool-use appropriateness, not exact trace; sampled human review weighted higher for agent steps |
| Tool allowlist scoped too broadly out of convenience | Reintroduces the unbounded-scope risk D-89 identified | Reviewed per workflow; write/irreversible tools require explicit justification (`16`) |
| Iteration ceiling set too low for genuinely complex goals | Frequent clean failures, poor user experience | Ceiling tuned from observed termination-reason distribution (`54`); escalation to a redesigned step, not a raised ceiling, is the default response |
| Reflection loops consume the entire budget without reaching the goal | Wasted cost with no output | Termination criteria required to be checkable independent of iteration count |
| Multi-agent-shaped workflows recreate negotiation informally through step chaining | The audit and evaluation problem returns by another path | Each step's output remains a typed contract; review checks for steps whose real function is passing control rather than adding structure |
| Agent step trace volume overwhelms tracing infrastructure | Cost, storage | Sampling policy for agent traces mirrors `38`'s AI-generation retention (100%, tiered) |

## Dependencies

- **Depends on:** multi-provider (`50`), gateway (`51`), prompt engine (`52`),
  context/memory/knowledge (`55`), AI integration (`40`).
- **Depended on by:** evaluation (`57`), AI security (`58`).

## Future Improvements

- Write ADR-0003 (bounded agents) before implementation, per the amendment
  requirement in `50`.
- Publish the initial tool registry and its capability classes before the first
  agent step ships.
- Evaluate whether termination-criteria design needs a dedicated authoring
  pattern once several agent steps exist to compare.
