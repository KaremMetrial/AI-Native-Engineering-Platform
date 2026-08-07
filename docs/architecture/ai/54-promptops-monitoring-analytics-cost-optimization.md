# PromptOps: Monitoring, Analytics, Cost Tracking and Optimization

## Purpose

Specify how prompts and model usage are observed in production, how cost is
attributed, and how the optimization loop works. `53` governs how a prompt
reaches production; this document governs what happens once it is there.

## Scope

**In scope:** runtime monitoring signals, quality proxies, drift detection,
analytics, cost attribution and budgets, and the optimization loop.

**Out of scope:** deployment (`53`), offline evaluation (`57`), general
observability (`38`).

---

## Monitoring

**Decision.** Every prompt version is monitored on operational and quality
signals, with quality inferred from user behaviour rather than measured only
offline.

**Reasoning.** Offline evaluation (`57`) tells us how a prompt performs on
curated inputs at the moment of release. It cannot tell us how it performs on
real inputs a month later, on a model the provider has quietly adjusted, for a
tenant whose conventions have drifted. Production signals are ground truth; the
golden set is an early-warning system.

The difficulty is that quality has no direct runtime measurement — there is no
error code for "plausible but wrong." So quality is inferred from what users do
with the output, which is a genuine measurement of usefulness (G4).

### Signals per prompt version

| Class | Signal | Reads as |
| --- | --- | --- |
| **Operational** | Latency p50/p95/p99 | Performance, and tail behaviour per provider |
| | Error and timeout rate | Reliability |
| | Schema validation failure rate | **Fastest quality proxy available** |
| | Retry rate; tier escalation rate | Output quality falling below first-attempt threshold |
| | Cache hit rate | Cost efficiency and rendering determinism |
| **Usage** | Calls, tokens in/out, cached share | Volume and cost drivers |
| **Quality proxies** | **Approval rate without major edit** | G4 directly |
| | **Edit distance, generated → approved** | How much rework the user did |
| | **Regeneration rate** | User rejected the first attempt |
| | Time-to-approval | Review friction |
| | Abandonment rate | Output not worth finishing |
| **Cost** | Cost per call, per artifact type | $-2 |

**Schema validation failure rate is the highest-value operational signal**
because it is deterministic, immediate, and correlates with quality collapse. A
prompt that starts producing malformed output has usually started producing worse
output generally, and this signal fires within minutes rather than waiting for
approval data.

**Edit distance is the highest-value quality signal** and the most direct measure
of G4. A draft accepted with a comma changed is a good draft; one rewritten
wholesale is a bad draft that happened to be approved because the user needed to
move on. Approval rate alone hides that distinction.

### Cardinality

Per-prompt-version metrics are bounded — a few hundred active versions — so
prompt version *is* an acceptable metric label. **Tenant is not** (D-417); per-
tenant quality analysis uses traces and the cost records in the database, not
metric labels.

---

## Drift Detection

**Decision.** Output characteristics are monitored for distribution shift, and
material drift raises an alert even when no deployment occurred.

**Reasoning.** Three things change underneath a stable prompt version:

1. **Provider-side model changes.** Even with pinned identifiers (D-279),
   providers apply safety and serving changes that alter behaviour. A pin
   constrains the model version; it does not freeze the model's behaviour
   entirely.
2. **Input distribution shift.** Tenants change what they put in. A prompt tuned
   on Phase 1 discovery notes meets Phase 3 inputs that look different.
3. **Context composition shift.** As a tenant's graph grows, retrieval returns
   different material, so the same prompt receives systematically different
   grounding.

None of these produces an error. All degrade quality silently, and all are
invisible to a deployment-triggered evaluation regime.

**Monitored distributions:** output length, schema field-population rates,
refusal and hedge frequency, confidence-expression frequency, token usage per
call, and quality-proxy trends.

**Response to detected drift:** re-run the golden set against the current
production configuration. If offline scores held while production signals fell,
the input distribution moved and the golden set is stale (D-98). If offline
scores also fell, the model behaviour moved and the pinned version needs
re-evaluation or replacement.

**That diagnostic split is the point of running both**: production signals detect
the problem, offline re-evaluation localizes its cause.

---

## Analytics

Aggregations serving decisions rather than curiosity. Each answers a question
someone acts on.

| View | Question it answers | Acted on by |
| --- | --- | --- |
| **Cost per artifact type** | Which capabilities are expensive to produce? | Routing, prompt optimization ($-2) |
| **Quality-cost frontier per workflow** | Which model gives acceptable quality most cheaply? | Routing policy (`51`) |
| **Cross-provider comparison** | Where does each provider win? | Registry scores, promotion decisions (`50`) |
| **Prompt version history** | Did that change help? | Iteration; rollback decisions |
| **Per-tenant cost and margin** | Which tenants are unprofitable? | Pricing, quotas (G5) |
| **Cache effectiveness** | Where is caching failing to engage? | Rendering determinism, prompt structure |
| **Failure taxonomy** | Why do generations fail? | Prompt fixes, guardrail tuning |
| **Escalation analysis** | Which workflows routinely need a higher tier? | Tier assignment — a workflow escalating half the time is mis-tiered |

**The quality-cost frontier is the analysis that makes multi-provider pay for
itself.** Without it, provider selection is preference; with it, it is a measured
trade-off per workflow — which is the entire justification for the multi-provider
cost (`50`).

**Escalation analysis is the cheapest optimization signal available.** A workflow
escalating from balanced to frontier on half its calls is paying for two calls
where one correctly-tiered call would do — visible immediately, fixable by
changing one registry field.

---

## Cost Tracking

**Decision.** Every call's cost is attributed along a complete chain, stored in
the database (D-444), and reconciled monthly against provider invoices (D-445).

### Attribution chain

```
   call → step → workflow run → job → artifact version → project → tenant
     │
     └── also: prompt version · model · provider · actor
```

Any node answers "what did this cost?" — a tenant's monthly spend, a workflow's
cost per run, an artifact's total cost of production including retries and
escalations.

**Artifact-level attribution is what makes $-2 real.** "AI cost is up 12%" is
unactionable; "BRD synthesis costs 40% more than last month, driven by context
size growth" is a task.

### Accounting rules

| Rule | Reason |
| --- | --- |
| Cached and uncached input tokens priced separately (D-553) | Cache reads cost a fraction; conflating them overstates savings and misprices |
| Failed calls are recorded (D-443) | They consumed tokens; omitting them breaks reconciliation |
| Retries and escalations attributed to the originating artifact | A generation needing three attempts genuinely cost three attempts |
| Shadow evaluation cost tracked separately | It is R&D spend, not cost of goods sold — mixing them distorts gross margin |
| Evaluation runs tracked separately | Same reason |

**Separating shadow and evaluation spend from production spend matters for G5.**
Both are real money and neither is attributable to serving a customer. Counted as
COGS they understate gross margin and make the platform look less viable than it
is; ignored entirely they understate total burn.

### Budget hierarchy

Enforced before dispatch (D-442), checked in order:

```
   platform daily ceiling      ← circuit breaker against runaway spend
      └── tenant monthly budget      ← contractual/plan limit ($-4)
            └── project budget             ← optional tenant-set
                  └── workflow run budget        ← bounds a single run
                        └── single call maximum       ← bounds one call
```

**The platform ceiling is a safety net, not a business control.** It exists so
that a bug — an agent loop, a runaway retry, a misconfigured batch — cannot
produce an unbounded bill before anyone notices. It should never bind in normal
operation; if it does, that is itself an alert.

---

## Optimization

**Decision.** Optimization is a measured loop. Automated analysis **proposes**
changes; humans review and approve them. Prompts are never auto-modified in
production.

**Reasoning.** Automatic prompt optimization — a system that rewrites its own
prompts based on metrics — is achievable and is the wrong thing to build here.
It would mean production behaviour changing without review, without evaluation
against the adversarial suite, and without anyone able to explain why an artifact
was produced the way it was. In a platform whose output becomes requirements and
contracts, and whose lineage must be explicable years later (U-4), that is
unacceptable.

The same reasoning underlies D-36: the system proposes, humans confer authority.
Applying it to our own prompts as well as to customer artifacts is consistency,
not caution.

**Alternatives.** *Fully automated optimization* — fastest improvement,
ungoverned behaviour change, unexplainable lineage. *Purely manual* — governed
and slow, and misses opportunities nobody thought to look for. *Automated
proposal with human gate* *(chosen)* — the search is automated, the decision is
not.

### The loop

```
   1 MEASURE     analytics surface a candidate: high cost, low quality,
                 poor cache engagement, frequent escalation
   2 HYPOTHESIZE a specific change with an expected effect
   3 EVALUATE    offline golden set + cross-provider (`57`)
   4 REVIEW      human, per `53`'s checklist
   5 SHADOW      real inputs, no user impact
   6 CANARY      progressive rollout with automatic rollback
   7 CONFIRM     production signals hold; baseline updated
```

### Optimization levers, by typical yield

| Lever | Mechanism | Risk |
| --- | --- | --- |
| **Tier downgrade** | Route to a cheaper model where evaluation shows quality holds | Quality regression — gated by evaluation |
| **Context precision** | Retrieve fewer, better-ranked items (D-92) | Under-grounding — gated by evaluation |
| **Cache engagement** | Fix rendering determinism; move stable content into the prefix | None — pure win when the defect is real |
| **Output length discipline** | Constrain verbosity; output tokens usually cost more than input | Truncated usefulness |
| **Prompt compression** | Shorter instructions with equal effect | Behaviour change — evaluated like any prompt change |
| **Batching** | Non-interactive work at batch rates | Latency — only where no user waits |
| **Step elimination** | Remove steps compensating for model limitations that no longer exist | Quality regression |

**Step elimination deserves emphasis.** As models improve, workflow steps added
to compensate for earlier limitations — self-review passes, output repair steps,
elaborate formatting instructions — become unnecessary. They are rarely removed,
because nothing fails when they persist. Periodically testing whether a step
still earns its cost is among the highest-yield optimizations available, and it is
the concrete form of `28`'s expectation that the AI layer sheds scaffolding as
models advance.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-601 | Quality is monitored in production via behavioural proxies, not only offline | Offline evaluation describes curated inputs at release; production is ground truth |
| D-602 | Edit distance from generated to approved is the primary quality signal | Approval rate alone hides wholesale rewrites approved under time pressure |
| D-603 | Schema failure rate is the fastest quality proxy and gates canaries | Deterministic, immediate, and correlated with quality collapse |
| D-604 | Prompt version is an acceptable metric label; tenant is not | A few hundred versions is bounded; 50k tenants is not (D-417) |
| D-605 | Output distribution drift is monitored and alerts absent any deployment | Provider behaviour, input distribution and context composition all shift silently |
| D-606 | Drift response re-runs the golden set to localize the cause | Production signals detect; offline re-evaluation diagnoses |
| D-607 | Cost attributed along the full chain to artifact and tenant | "AI cost is up 12%" is unactionable; per-artifact attribution is a task |
| D-608 | Shadow and evaluation spend tracked separately from production spend | They are R&D, not COGS; conflating them distorts gross margin (G5) |
| D-609 | Five-level budget hierarchy with a platform ceiling as a safety net | A bug must not produce an unbounded bill before anyone notices |
| D-610 | Platform ceiling binding is itself an alert | It should never bind in normal operation |
| D-611 | **Optimization is automated in analysis, never in application** | Self-modifying prompts mean ungoverned behaviour change and unexplainable lineage |
| D-612 | Workflow steps are periodically tested for whether they still earn their cost | Scaffolding compensating for past model limits is never removed because nothing fails when it persists |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Quality proxies mislead — users edit for style, not correctness | Optimization targets the wrong thing | Proxies calibrated against sampled human review (`57`); no single proxy acted on alone |
| Drift detection produces false alarms | Alert fatigue | Thresholds tuned from observed variance; drift alerts are tickets, not pages |
| Cost attribution drifts from provider invoices | Margin decisions on wrong figures | Monthly reconciliation (D-445); discrepancy beyond tolerance is a defect |
| Optimization pressure degrades quality below G4 | Product value collapses (CR-2) | Every optimization gated by evaluation; quality floor is non-negotiable |
| Tier downgrade adopted broadly then regresses subtly | Slow quality erosion | Post-adoption production signals monitored; baseline updated only after confirmation |
| Platform ceiling too low, blocking legitimate load | AI unavailable during a spike | Ceiling set well above expected peak; binding raises an alert for review, not silent rejection |
| Automated proposals accumulate unreviewed | Backlog of unrealized savings | Proposals expire; recurring proposals escalate in priority |

## Dependencies

- **Depends on:** gateway (`51`), prompt engine (`52`), deployment (`53`),
  observability (`38`), evaluation (`57`).
- **Depended on by:** routing policy (`51`), registry scores (`50`).

## Future Improvements

- Calibrate quality proxies against human review labels to establish which
  correlate with genuine quality rather than with user haste.
- Add automated step-elimination candidates: flag steps whose removal does not
  regress the golden set.
- Publish per-workflow unit economics once Phase 1 telemetry provides baselines.
