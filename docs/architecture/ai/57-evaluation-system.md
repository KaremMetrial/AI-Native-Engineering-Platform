# Evaluation System

## Purpose

Specify the architecture that measures AI quality: how golden sets are built and
maintained, how judges are constructed and calibrated, how evaluation runs and
gates, and how evaluation extends across the new surfaces this set introduces —
multiple providers and bounded agents.

`10` establishes the evaluation principle; `53` uses it to gate deployment. This
document is the evaluation system itself.

## Scope

**In scope:** the evaluation architecture, golden set management, judge design
and calibration, agent-step evaluation, cross-provider evaluation, human-in-the-
loop calibration, and the evaluation data model.

**Out of scope:** deployment gating mechanics (`53`), production monitoring
(`54`).

---

## Position

**Without evaluation, "the model is good" is an opinion.** This system exists to
convert that opinion into a measured, falsifiable, trackable claim — the same
standard the charter requires of every other engineering claim in this platform.

**Decision.** Evaluation is a first-class subsystem with its own data model,
independent of the workflows it evaluates, and every quality claim in the
platform traces to a specific evaluation run.

---

## Evaluation Layers

Restated from `10` and `53`, with the mechanism for each specified.

```
   ┌─────────────────────────────────────────────────────────┐
   │ 1  DETERMINISTIC     schema, structural, links resolve    │  HARD GATE
   ├─────────────────────────────────────────────────────────┤
   │ 2  GOLDEN SET        curated inputs, reference outputs    │  REGRESSION
   ├─────────────────────────────────────────────────────────┤
   │ 3  LLM-AS-JUDGE      rubric scoring, calibrated           │  REGRESSION
   ├─────────────────────────────────────────────────────────┤
   │ 4  ADVERSARIAL        injection, jailbreak, leakage        │  HARD GATE
   ├─────────────────────────────────────────────────────────┤
   │ 5  HUMAN SAMPLING      weekly, calibration ground truth    │  ADVISORY
   ├─────────────────────────────────────────────────────────┤
   │ 6  PRODUCTION SIGNAL   edit distance, approval, regen (`54`)│ ADVISORY→GATE
   └─────────────────────────────────────────────────────────┘
```

**Layer 6 deserves explicit status here: it starts advisory and becomes a gate
once correlated.** Production signals are ground truth for usefulness (G4), but
naively gating on them before they are calibrated against layer 5 risks gating on
noise. Once a signal is shown to correlate with human judgment (below), it is
promoted to a canary gate in `53`.

---

## Golden Set Architecture

### Structure

| Field | Purpose |
| --- | --- |
| `case_id` | Identity |
| `workflow` | Which capability this exercises |
| `input` | The complete input, including realistic context |
| `reference_output` | Expert-authored or expert-approved answer |
| `rubric_ref` | Which rubric grades this case (`52` — colocated with the prompt) |
| `difficulty_tier` | easy · representative · edge_case · adversarial-adjacent |
| `provenance` | **Curated** or **promoted from production** (shadow divergence, `53`) |
| `applicable_models` | Which providers/tiers this case is valid for |

**`applicable_models` matters specifically for multi-provider evaluation.** A
case testing extended reasoning is meaningless for a model without that
capability — it would either be skipped (silently reducing coverage) or scored
unfairly (penalizing a capability gap that routing already excludes via `50`'s
capability matching). Declaring applicability keeps coverage honest per model.

### Composition discipline

**Decision.** Golden sets are not uniformly random samples; they are
deliberately stratified across difficulty tiers, with **edge cases
overrepresented relative to their production frequency**.

**Reasoning.** A set dominated by easy cases (which is what random production
sampling produces, since most inputs are easy) produces high scores that hide
failure on the cases that matter — the sparse discovery input, the contradictory
requirement, the unusually long document. Overrepresenting hard cases means the
aggregate score is sensitive to exactly the failures a smooth average would
mask.

**Growth (D-98, restated with mechanism):** every production quality incident —
a bad approval, a support escalation, a shadow-evaluation divergence flagged as
material — adds a case. This is what keeps the set anchored to real failure
modes rather than to what the team imagined might go wrong at design time.

**Minimum size per workflow:** large enough for statistically meaningful
regression detection at the tolerance used in `53` — reviewed per workflow as
production volume clarifies natural variance, not fixed a priori.

---

## Judge Design

### Deterministic assertions

Cheapest and most reliable: schema conformance, required-section presence,
traceability links resolve, no placeholder text, word-count and structural
bounds. Written once per output contract, run on every case, gate hard.

### LLM-as-judge

**Decision.** Judges score on **decomposed rubric dimensions**, never a single
holistic score. Every dimension carries a concrete definition and worked
examples.

| Dimension | Definition anchor |
| --- | --- |
| **Groundedness** | Every substantive claim traces to provided context or is explicitly marked as an assumption |
| **Completeness** | Every required element of the output contract is meaningfully populated, not merely present |
| **Specificity** | Claims are concrete and actionable, not generic boilerplate that could apply to any project |
| **Consistency** | No internal contradiction across the output |
| **Fabrication (inverse)** | No claim invents fact not supported by context or explicit assumption |

**Reasoning for decomposition.** A single "quality: 7/10" score is not
actionable — it does not say what to fix, and it hides the case where an output
is highly specific but ungrounded (confident fabrication, the single most
damaging failure mode per `10`) inside an average that looks acceptable.
Decomposed scores let regression analysis identify *which* property changed,
directing the fix rather than a guess.

**Judge prompts follow the same discipline as any other prompt** (`52`, `53`):
versioned, tested for determinism, reviewed on change. A judge is itself an AI
call and is subject to the same governance — an ungoverned judge is a hidden,
unaudited quality gate.

### Calibration

**Decision.** Judges are calibrated against layer 5 (human sampling) on a
recurring schedule, and a judge whose agreement with human raters falls below
threshold is **not trusted** until recalibrated.

**Reasoning.** `10` states honestly that LLM-as-judge correlates imperfectly and
can share the biases of the model being judged — and this concern is now sharper,
because we route across multiple providers. A judge built on one provider's model
may systematically favor that provider's outputs. Calibration is what catches
this rather than assuming it away.

**Mechanism:** weekly human-sampled cases are scored by both the judge and a
human rater; agreement (correlation, not exact match) is tracked per dimension
and per candidate-model-being-judged. A judge scoring outputs from its own
underlying model is flagged for closer scrutiny of that specific pairing.

**Alternatives.** *Trust the judge unconditionally* — the honest-limitation
concern in `10` made concrete and unaddressed. *Human review only, no judge* —
does not scale to the evaluation volume `53`'s pipeline requires. *Multiple
judges voting* — reduces single-model bias and multiplies evaluation cost;
adopted for high-stakes workflows (frontier-tier, commercial-facing) where the
cost is justified, not universally.

---

## Cross-Provider Evaluation

The evaluation dimension this document adds beyond `10`'s single-provider
framing.

**Decision.** A workflow's registry score (`50`) is per **(workflow, model)**
pair, not per workflow alone. Promoting a model to `active` for a workflow
requires the full golden set run against that pairing, not an assumption of
parity from a different workflow.

**Reasoning.** Provider strengths are genuinely uneven across task types — one
model may excel at long-document synthesis and lag at strict schema adherence.
Treating "this model is good" as a workflow-independent fact would misroute
exactly the cases where the differences matter most, defeating the purpose of
having a multi-provider registry at all (`50`, D-566).

**Cross-provider run also surfaces normalization defects** (`50`): if a
workflow's score drops sharply on one provider despite comparable model
capability, the more likely explanation is an adapter defect in normalization
than a genuine capability gap — this is a diagnostic signal, not only a routing
input.

**Judge-model independence:** where feasible, the judge model differs from the
candidate model under evaluation, specifically to reduce the self-favoring bias
identified above.

---

## Agent Step Evaluation

Extends the standard layers to accommodate the path-variable execution model of
`56`'s agent steps.

| Standard layer | Adaptation for agent steps |
| --- | --- |
| Deterministic | Applies to the step's final output contract, unchanged |
| Golden set | Graded on **outcome**, not on requiring an exact tool-call trace — the same goal reached via a different valid path is not a regression |
| LLM-as-judge | Additional dimension: **tool-use appropriateness** — were the tools used proportionate to the goal, or was there evident thrashing |
| Adversarial | Extended: attempts to induce excess tool calls, prompt the agent toward disallowed tools, or exhaust the iteration budget maliciously |
| Human sampling | **Weighted higher** for agent steps than for deterministic generation — path variability makes automated judgment less reliable, so human calibration carries more weight here specifically |

**Additional agent-specific metric: termination-reason distribution.** A healthy
agent step terminates on "goal met" the large majority of the time. A rising
share of iteration-ceiling terminations is a leading indicator of a
mis-scoped goal or an insufficient tool allowlist, and it is monitored
continuously (`54`) rather than only at evaluation time — because it changes
between evaluation runs as production inputs drift.

---

## Human-in-the-Loop Calibration

The mechanism that keeps layers 3 and 6 honest.

```
   Weekly sample: stratified across workflow, model, tier
        │
   Human expert rates: same rubric as the LLM judge
        │
   ┌────▼─────────────────────────────────────────────┐
   │ Compare: human rating vs. judge score              │
   │          human rating vs. production signal (`54`) │
   └────┬─────────────────────────────────────────────┘
        │
   ├── judge agreement ≥ threshold → judge trusted
   ├── judge agreement < threshold → judge flagged, reweighted or replaced
   ├── production signal correlates → promoted from advisory to gate
   └── divergent cases → become golden set candidates
```

**This loop is what makes the entire automated evaluation system trustworthy
rather than merely self-referential.** An evaluation system that only checks
itself against itself cannot detect its own drift; human sampling is the
external anchor.

---

## Evaluation Cost and Scheduling

Restated with the multi-provider and agent extensions:

| Trigger | Scope |
| --- | --- |
| Prompt version change | Full golden set, target model(s) only |
| New model promotion candidate | Full golden set, candidate model, all applicable cases |
| Nightly | Full golden set, all active (workflow, model) pairs |
| Weekly | Human sampling and judge calibration |
| Shadow divergence flagged | Candidate case for golden set |

**Evaluation is not part of the standard PR pipeline** (D-143) — extended here:
cross-provider evaluation specifically is reserved for prompt changes and
promotion candidates, not every commit, because it multiplies cost by the number
of candidate providers.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-638 | Evaluation is a first-class subsystem; every quality claim traces to a specific run | Converts "the model is good" from opinion into a falsifiable, trackable claim |
| D-639 | Production signal (layer 6) starts advisory and is promoted to a gate once correlated with human judgment | Gating on an uncalibrated signal risks gating on noise |
| D-640 | Golden set cases declare `applicable_models` | Testing an unsupported capability either hides coverage gaps or penalizes an excluded capability unfairly |
| D-641 | Golden sets are stratified with edge cases overrepresented relative to production frequency | Random sampling produces high scores that hide exactly the failures that matter |
| D-642 | Judges score decomposed rubric dimensions, never a single holistic score | A single score is not actionable and hides confident fabrication inside an acceptable-looking average |
| D-643 | Judge prompts are versioned and governed like any other prompt | An ungoverned judge is a hidden, unaudited quality gate |
| D-644 | Judges are calibrated against human sampling on a recurring schedule; low agreement suspends trust | Judges can share the biases of the model they are built on, sharper now with multiple providers in play |
| D-645 | Multiple judges used for high-stakes workflows specifically, not universally | Reduces single-model bias where the cost is justified |
| D-646 | Registry scores are per (workflow, model) pair; promotion requires the full golden set on that pairing | Provider strengths are task-dependent; workflow-independent scoring misroutes exactly where differences matter |
| D-647 | A sharp cross-provider score drop is treated as a likely adapter defect before a capability gap | Normalization bugs and genuine gaps look identical without this default assumption |
| D-648 | Agent steps are graded on outcome, not on a required tool-call trace | A different valid path to the same goal is not a regression |
| D-649 | Human sampling weight is higher for agent steps than for deterministic generation | Path variability makes automated judgment less reliable specifically here |
| D-650 | Termination-reason distribution is monitored continuously, not only at evaluation time | It drifts with production inputs between evaluation runs |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Golden set stagnates and stops reflecting real failure modes | Evaluation passes while production quality degrades | Continuous growth from production incidents and shadow divergences (D-98) |
| Judge self-favoring bias goes undetected | Systematic mis-scoring toward one provider | Calibration tracked per judge-model/candidate-model pairing; judge-model independence where feasible |
| Cross-provider evaluation cost limits how often it runs | Stale routing decisions | Reserved for prompt changes and promotion candidates; nightly full run on active pairs only |
| Agent-step evaluation trusted as much as deterministic evaluation | Overconfidence in path-variable output | Human sampling weighted higher; termination-reason monitored as a leading indicator |
| Production signal promoted to a gate before correlation is solid | Gating on noise | Explicit correlation threshold before promotion (`53`) |
| Rubric dimensions drift from what the prompt was designed to produce | Evaluation measures the wrong thing | Rubrics colocated with prompts (D-585); reviewed together on prompt change |
| Human sampling capacity does not scale with workflow count | Calibration coverage thins | Sampling stratified to prioritize highest-volume and highest-risk workflows first |

## Dependencies

- **Depends on:** AI strategy (`10`), prompt engine and library (`52`), PromptOps
  (`53`, `54`), multi-provider (`50`), workflow and agent engine (`56`).
- **Depended on by:** deployment gating (`53`), routing (`51`), AI security
  (`58`) for adversarial-suite integration.

## Future Improvements

- Publish per-workflow golden set minimum sizes once production variance is
  measured, replacing the current judgment-based sizing.
- Add automated judge-bias detection that flags a judge/candidate pairing before
  the weekly calibration cycle catches it.
- Explore learned reward models trained on accumulated human sampling data, once
  sufficient volume exists, as a supplement to prompted LLM judges.
