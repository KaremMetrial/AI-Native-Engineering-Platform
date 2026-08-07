# PromptOps: Versioning, Testing, Review and Deployment

## Purpose

Define the lifecycle a prompt change passes through from authoring to
production. A prompt change is a behaviour change with the same blast radius as a
code change, and it deploys more often — so it needs a lifecycle with the same
rigour and considerably more speed.

## Scope

**In scope:** prompt versioning, the test suite, review requirements, shadow
evaluation, progressive deployment, rollback and emergency change.

**Out of scope:** prompt composition (`52`), runtime monitoring and analytics
(`54`), evaluation mechanics (`57`).

---

## The Governing Tension

Prompts must deploy **faster than code** — iteration is how quality improves —
and must be governed **as strictly as code**, because an unreviewed prompt change
can degrade every artifact the platform produces.

**Decision.** Prompts deploy through an independent, faster pipeline that
enforces the same gates. Speed comes from decoupling the deployment unit, never
from relaxing the gates.

**Reasoning.** The two obvious resolutions are both wrong. Coupling prompt
deploys to application deploys makes iteration slow enough that people stop
iterating, and quality stagnates. Allowing live prompt editing through an admin
UI — the common product answer — means unreviewed, untested, unversioned changes
to the system's most behaviour-defining component, with no way to correlate a
quality regression with what caused it (D-93).

Decoupling the *unit* while keeping the *gates* gets both: a prompt change can
reach production in minutes, having passed evaluation, review and canary.

**Alternatives.**

| Alternative | Assessment |
| --- | --- |
| **Prompts deploy with application code** | Simplest; iteration cadence drops to the release cadence, and prompt work queues behind unrelated changes |
| **Live editing in an admin UI** | Fastest possible iteration; ungoverned behaviour change, no correlation between regression and cause, no rollback story |
| **Prompts as runtime config in a database** | Fast and centrally managed; same governance gap as live editing unless a full review pipeline is rebuilt around it |
| **Independent pipeline, same gates** *(chosen)* | Fast and governed; costs a second pipeline |

**Trade-offs.** A second deployment pipeline to build and maintain, and two
things in production that can be at different versions — requiring the lineage
record to capture both (D-93).

**Benefits.** Iteration measured in minutes. Every change evaluated, reviewed,
canaried and revertible.

**Long-term impact.** The AI layer churns fastest of anything in the system
(`23`). Whether that churn is governed or chaotic is decided by this pipeline.

---

## Versioning

**Decision.** Prompt definitions are **immutable and content-addressed**, with a
human-readable semantic version alongside the content hash.

```
   PromptVersion
   ├── definition_id        brd_synthesis/system
   ├── semantic_version     3.2.0
   ├── content_hash         sha256 of the canonical serialized definition
   ├── resolved_hash        hash including all included fragment versions
   ├── created_at, author, review_ref
   └── status               draft · candidate · active · deprecated · retired
```

**`resolved_hash` is the important one.** A definition's own content may be
unchanged while an included fragment changes beneath it — the rendered prompt
differs, the behaviour differs, and the definition's own hash does not move.
Hashing the fully resolved composition means every behaviour-affecting change
produces a new identity.

Without it, a shared safety preamble revision would silently alter twenty
workflows whose recorded prompt versions all appear unchanged, making a
subsequent quality regression un-attributable.

**Semantic versioning conveys intent to reviewers:**

| Bump | Meaning |
| --- | --- |
| **Major** | Output shape or contract changes — downstream consumers affected |
| **Minor** | Behaviour changes within the same contract |
| **Patch** | Wording clarification not expected to change behaviour — **still evaluated**, because "not expected to" is a hypothesis |

**Every generation records the resolved hash** (D-93), so any artifact can be
traced to the exact composition that produced it, years later.

---

## Testing

Five layers, running at different points and gating differently.

| Layer | Checks | When | Gate |
| --- | --- | --- | --- |
| **1 · Static** | Segment discipline, no duplicate instruction text, typed inputs declared, output schema valid, no secrets | Every commit | **Hard** |
| **2 · Render** | Renders for every target model; deterministic (rendered twice, byte-identical); within token budget | Every commit | **Hard** |
| **3 · Contract** | Output validates against the declared schema on a sample; required fields present; traceability links resolve | Every prompt change | **Hard** |
| **4 · Golden set** | Aggregate quality scores vs recorded baseline (`57`) | Every prompt change | **Regression threshold** |
| **5 · Adversarial** | Injection corpus, jailbreak attempts, PII leakage, schema-breaking inputs | Every prompt change | **Hard** |

**Layers 1, 2, 3 and 5 gate hard because they are deterministic.** Layer 4 gates
on aggregate regression because output is non-deterministic and per-run pass/fail
would be flaky — and flaky gates get ignored (D-96).

**The determinism check in layer 2 is cheap and high-value:** rendering the same
definition and inputs twice and comparing bytes catches the entire class of
silent cache-degrading defects (D-579) at commit time.

**Patch versions are evaluated too.** A wording change intended as clarification
frequently changes behaviour — the model is sensitive to phrasing in ways authors
do not predict. Exempting patches would exempt the most common change type from
the only check that would catch its effect.

---

## Review

Two reviewers, as for any AI-affecting change (D-163), with a prompt-specific
checklist.

- [ ] The change addresses a stated problem — a failure case, an evaluation gap,
      a cost target — not a hunch
- [ ] Evaluation results attached; no regression beyond tolerance
- [ ] Cross-provider results attached where the workflow routes to more than one
- [ ] Segment discipline maintained; untrusted content still in an
      `UntrustedBlock`
- [ ] Cache classes correct — no volatile content moved into the stable prefix
- [ ] Token budget impact assessed
- [ ] Fragment blast radius reviewed if a shared fragment changed (D-586)
- [ ] Output contract unchanged, or the version bump is major and consumers
      identified
- [ ] Adversarial suite passes
- [ ] Rollout plan stated: canary percentage and success criteria

**"Addresses a stated problem" is the checklist item that matters most.** Prompt
engineering invites undirected tinkering — small wording changes that feel better
and are unmeasurable. Requiring a stated problem and an evaluation result turns
prompt work into engineering rather than intuition.

---

## Shadow Evaluation

**Decision.** Before a candidate version receives live traffic, it runs in shadow
against real production requests: the candidate is invoked alongside the active
version, its output is recorded and compared offline, and **it is never returned
to a user**.

**Reasoning.** Golden sets are curated and finite; production inputs are messy
and unbounded, and the failure modes that matter most appear on inputs nobody
thought to curate. Shadow evaluation tests a candidate against reality without
risking a single user's output.

It also generates the next generation of golden set cases: any production input
where candidate and active diverge materially is a case worth curating (D-98).

**Alternatives.** *Golden sets only* — safe and misses input distributions nobody
anticipated. *Straight to canary* — real signal, and real users receive
unvalidated output. *Offline replay of logged inputs* — close, and misses live
context assembly, which is itself a variable.

**Trade-offs — this is the expensive option and it is worth naming why:** shadow
runs **double inference cost** for the sampled traffic. Mitigated by sampling
rather than shadowing everything, and by shadowing only for minor and major
versions where behaviour change is expected.

**Benefits.** Real-input validation with zero user risk. Divergence analysis
before exposure. Golden set growth from genuine production cases.

---

## Deployment

**Decision.** Progressive rollout by traffic percentage, with automatic rollback
on quality or error regression.

```
   candidate  ──▶  shadow (sampled, no user impact)
        │
        ▼  promote
   canary 5%   ── monitor: schema failure, latency, cost, retry rate,
        │                  early quality proxies (`54`)
        ▼  hold ≥ 1h and ≥ N calls
   canary 25%  ── monitor
        │
        ▼  hold
   100%        ── active; previous version retained as instant rollback target
```

**Rollout is per workflow and per model**, not global: a prompt may perform well
on one model and regress on another, and a global rollout would average that into
invisibility.

**Automatic rollback triggers** — any one, immediately:

| Signal | Threshold |
| --- | --- |
| Schema validation failure rate | Above baseline + tolerance |
| Error or timeout rate | Above baseline |
| Cost per call | Materially above baseline |
| Tier escalation rate | Above baseline — a proxy for output quality falling |
| Early quality proxy (regeneration rate) | Above baseline |

**The hold period matters as much as the percentage.** A canary evaluated over
ten calls proves nothing; quality signals are statistical and need volume. The
minimum-call threshold prevents a low-traffic workflow from promoting on noise.

**Rollback is instantaneous** because versions are immutable and routing is
configuration — the previous version is still resolvable, so rollback is a
pointer change requiring no rebuild.

---

## Emergency Change

A prompt causing active harm — leaking content, producing unsafe output,
generating malformed artifacts at scale — needs a faster path.

| Action | Requirement |
| --- | --- |
| **Disable the workflow** (kill switch, D-119) | Immediate, single operator, audited |
| **Roll back to the previous version** | Immediate, single operator, audited |
| **Deploy a corrected version** | Expedited: two approvers, static and adversarial suites still run, golden set may be deferred |

**The adversarial and static suites are never skipped**, even in an emergency
(D-169's principle). The reason a change is urgent is never a reason injection
defence may lapse — and an emergency prompt change is exactly the circumstance in
which a rushed edit could open one.

**Disabling beats fixing under pressure.** The kill switch is the first response;
a corrected version follows once someone has understood the problem rather than
guessed at it.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-587 | Prompts deploy through an independent pipeline enforcing the same gates | Coupling to code deploys stalls iteration; live editing removes governance |
| D-588 | Prompt versions are immutable and content-addressed | Rollback becomes a pointer change; lineage stays reproducible |
| D-589 | **`resolved_hash` covers all included fragment versions** | Otherwise a shared fragment change alters many workflows whose recorded versions appear unchanged |
| D-590 | Patch versions are evaluated like any other | Models are sensitive to phrasing in ways authors do not predict |
| D-591 | Five test layers; four gate hard, golden set gates on aggregate regression | Deterministic checks can gate hard; non-deterministic ones would be flaky and then ignored |
| D-592 | Determinism verified by rendering twice and comparing bytes | Catches the entire class of silent cache-degrading defects at commit time |
| D-593 | Review requires a stated problem, not a hunch | Prompt work otherwise becomes undirected tinkering that cannot be evaluated |
| D-594 | Shadow evaluation against sampled production traffic before canary | Golden sets miss the input distributions that produce real failures |
| D-595 | Shadow divergences become golden set candidates | Grows the suite from genuine production cases |
| D-596 | Progressive rollout per workflow and per model, not globally | A prompt can improve on one model and regress on another; global rollout averages that away |
| D-597 | Canary promotion requires both a hold period and a minimum call count | Quality signals are statistical; low-traffic workflows would otherwise promote on noise |
| D-598 | Automatic rollback on schema failure, error, cost, escalation or regeneration regression | Escalation and regeneration rates are the fastest available quality proxies |
| D-599 | Emergency path may defer golden sets; never static or adversarial suites | Urgency is never a reason injection defence may lapse |
| D-600 | Kill switch is the first emergency response, correction second | Disabling is reversible and immediate; a rushed fix is neither |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Shadow evaluation cost becomes prohibitive | Skipped, losing pre-exposure validation | Sampled rather than exhaustive; reserved for minor and major versions |
| Canary quality signals too slow to catch a regression | Users receive degraded output during rollout | Fast proxies (schema failure, escalation rate) gate before slow ones; small initial percentage |
| Low-traffic workflows never accumulate canary signal | Rollout stalls or promotes blind | Minimum call count with a documented manual-promotion path requiring explicit sign-off |
| Prompt and code versions drift into an untested combination | Unexpected interaction | Lineage records both; compatibility asserted by contract tests |
| Emergency path used routinely | Governance eroded by normalization | Emergency usage tracked; frequency reviewed as a process health signal (D-171) |
| `resolved_hash` recomputation missed on fragment change | Silent behaviour change | Hash computed at build time from the dependency graph, not by hand |

## Dependencies

- **Depends on:** prompt engine and library (`52`), gateway (`51`), evaluation
  (`57`), versioning strategy (`27`), review process (`16`).
- **Depended on by:** monitoring and optimization (`54`), workflows and agents
  (`56`).

## Future Improvements

- Automate golden set candidate promotion from shadow divergences rather than
  curating by hand.
- Add per-tenant canary cohorts so rollout can start with tenants who have opted
  into early access.
- Publish prompt version compatibility with output contract versions so major
  bumps identify downstream consumers automatically.
