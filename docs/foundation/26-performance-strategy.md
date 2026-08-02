# Performance Strategy

## Purpose

Define how performance is achieved, measured, protected and traded off.
Distinct from scalability (`08`): scalability is handling *more*; performance is
being *fast*. A system can scale perfectly and be uniformly slow, and the
techniques for fixing each are different.

`04` states the performance targets. This document defines the method for
meeting them and keeping them met for a decade.

## Scope

**In scope:** performance as an engineering discipline — budgets, measurement,
optimization methodology, tail latency, perceived performance, regression
prevention, and when not to optimize.

**Out of scope:** capacity and load handling (`08`), infrastructure sizing
(`15`).

---

## Position

**Performance is a feature, a cost, and a constraint — in that order of
visibility and reverse order of how teams usually treat it.**

- **A feature:** users experience latency directly; a slow tool is a tool people
  avoid, and avoidance is the mechanism behind BR-1.
- **A cost:** inefficient code burns compute, and in our case burns inference
  tokens — the single largest variable cost ($-2).
- **A constraint:** the targets in `04` are thresholds, not aspirations. Below
  them is a defect.

**But performance is rank 6 of 7 architectural characteristics (`23`).** This is
deliberate and needs stating plainly: we meet the stated budgets and we do not
optimize beyond them at the expense of maintainability, correctness or
isolation. A codebase optimized past its requirement is a codebase that is
harder to change for a benefit nobody asked for.

---

## Performance Budgets

**Decision.** Every user-facing surface has a latency budget, decomposed into
per-component allowances, and the budget is enforced in CI.

**Reasoning.** A single global target ("the API should be fast") is unactionable
— when a page takes 900ms, nobody knows which part is over. Decomposed budgets
make the owner of a regression obvious and turn performance from a periodic
firefight into a continuous constraint.

Budgets also prevent the classic decay pattern: no single change makes anything
noticeably slower, but forty changes make the system twice as slow. Without a
budget there is never a moment where the regression is attributable, so it is
never addressed.

**Illustrative decomposition** for a requirements list view against P-1
(p95 < 300ms):

| Component | Budget |
| --- | --- |
| Edge and TLS | 20 ms |
| Authentication and tenant resolution | 15 ms |
| Authorization | 10 ms |
| Query execution (RLS applied) | 120 ms |
| Serialization | 25 ms |
| Network to client | 60 ms |
| Headroom | 50 ms |

**Headroom is explicit, not accidental.** A budget consumed exactly to its limit
has no capacity for growth, and the first data-volume increase breaches it.

**Alternatives considered.** *Global targets only* — simple, unactionable.
*Optimize when users complain* — reactive; by the time users complain, the cause
is distributed across dozens of changes and attribution is impossible.
*Per-change benchmarking without budgets* — detects change but provides no
threshold, so every regression becomes a negotiation.

**Trade-offs.** Budgets require maintenance and can be wrong — an inherited
budget that no longer matches reality gets ignored. They are reviewed at phase
boundaries against measured baselines.

**Benefits.** Regressions are attributable to a change while that change is
still in review. Optimization effort goes where the budget is actually
exceeded rather than where someone suspects.

**Long-term impact.** This is the mechanism that prevents the slow, unattributable
decay that makes ten-year-old systems sluggish for no identifiable reason.

---

## Measurement Before Optimization

**Decision.** No optimization is performed without a profile identifying the
dominant cost. Optimization without measurement is prohibited, including when
the cause seems obvious.

**Reasoning.** Engineering intuition about performance is unreliable in a
consistent direction: we optimize what we understand rather than what is slow.
The dominant cost in a system like ours is almost always IO — database round
trips, network hops, provider calls — while the code that *looks* expensive is
usually irrelevant. Time spent optimizing an algorithm that accounts for 2% of
latency is time spent making the code harder to read for a 2% ceiling on
improvement.

**Amdahl's law is the governing constraint:** the maximum gain from optimizing a
component is bounded by that component's share of total time. A 10× improvement
to something taking 5% of the time yields a 4.5% overall gain. Knowing the
distribution before starting is the difference between a meaningful improvement
and wasted effort.

**Alternatives.** *Optimize by intuition* — fast to start, usually targets the
wrong thing, and leaves harder-to-read code behind. *Optimize everything
defensively* — the premature optimization failure mode; costs maintainability
everywhere for benefit nowhere.

**Trade-offs.** Profiling infrastructure costs effort to build and run, and
profiling under realistic load requires realistic data — which is why synthetic
data generation is first-class tooling (D-115).

**Long-term impact.** A team disciplined about measurement accumulates real
knowledge of where its system spends time. A team that optimizes by intuition
accumulates folklore and complicated code.

### The method

1. **Confirm the budget is breached.** If it is not, stop — there is no problem.
2. **Profile under realistic conditions.** Production-scale data, representative
   multi-tenant distribution, realistic concurrency.
3. **Identify the dominant cost.** One component, usually.
4. **Understand *why* it is expensive** before changing it. A missing index and
   an N+1 query look identical in aggregate timing and have different fixes.
5. **Fix the dominant cost only.** Then re-measure — the dominant cost usually
   moves, and the second-order fix is frequently unnecessary.
6. **Verify against the budget**, and add a regression benchmark.
7. **Document why**, if the resulting code is less obvious than what it replaced
   (P6's boundary).

---

## Where Time Actually Goes

Known cost centres in this architecture, with the failure modes each produces.
Listed because knowing where to look first is most of diagnosis.

| Cost centre | Typical failure | Detection |
| --- | --- | --- |
| **Database round trips** | N+1 queries — the single most common performance defect in ORM-based systems | N+1 detection fails tests (D-134) |
| **Missing or wrong indexes** | Sequential scans that are fine at 10k rows and fatal at 10M | Query plan review; slow query log |
| **RLS policy evaluation** | Policy predicates that cannot use an index | Benchmark with policies enabled — a specific risk of D-54 |
| **Graph traversal depth** | Recursive CTEs degrading superlinearly with depth | Depth limits (P-3); benchmarks |
| **Serialization** | Serializing entire object graphs to return three fields | Payload size monitoring |
| **Over-fetching in retrieval** | Assembling 50 documents of context where 5 suffice | Directly visible as token cost (D-73) |
| **Provider latency** | Unbounded tail on model calls | Async by construction (P-9) — this is why it cannot hurt request latency |
| **Frontend bundle size** | Route-level bundles growing until first paint breaches P-4 | CI budget, blocking |
| **Chatty client-server interaction** | Six requests to render one view | Request-count monitoring per view |

**The two most important entries are the first and the sixth.** N+1 is the most
frequent defect and is fully preventable by tooling. Retrieval over-fetching is
the one unique to this platform: it degrades quality *and* cost *and* latency
simultaneously, which makes retrieval precision the highest-leverage performance
work in the AI path.

---

## Tail Latency

**Decision.** p99 is a first-class target, not a curiosity below p95.

**Reasoning.** Averages hide the experience that drives churn. In a multi-tenant
system, the p99 is not "1% of requests" — it is concentrated in the tenants with
the most data, who are the largest customers. A p50 of 80ms with a p99 of 4
seconds means the biggest customers experience a slow product while the
dashboard looks healthy.

Tail latency is also where **queueing effects** appear, and these are widely
misunderstood: as utilization rises, queueing delay grows non-linearly, and past
roughly 70–80% utilization latency degrades sharply for small increases in load.
This is why `08` autoscales on latency and concurrency rather than CPU (D-66) —
by the time average CPU looks busy, the tail has already collapsed.

**Sources of tail latency here:** connection pool exhaustion under burst; lock
contention on hot rows; cold caches after deploy; garbage collection and
process recycling; a large tenant's query on a shared resource; and retry storms
amplifying an upstream slowdown.

**Alternatives.** *Target the average* — the most common choice and the most
misleading; averages are dominated by the fast majority and say nothing about
who is suffering. *Target p95 only* — better, and still hides a p99 that is
twenty times worse, which is exactly where the largest tenants live. *Target the
maximum* — dominated by unrepresentative outliers (a deploy, a cold start), so
it produces noise rather than signal.

**Trade-offs.** Optimizing the tail often costs median performance — request
hedging, aggressive timeouts and circuit breakers all add overhead to the common
case. Worth it where the tail affects the largest customers.

**Benefits.** The customers who matter most commercially get an experience
matching what the dashboards claim. Tail work also surfaces genuine structural
problems — pool exhaustion, lock contention — that median-focused measurement
never reveals.

**Long-term impact.** Tail latency degrades silently as data grows. A system
never measured at p99 will develop a slow-for-big-customers problem that is
discovered through churn rather than through monitoring.

---

## Perceived Performance

**Decision.** Perceived responsiveness is engineered deliberately, and is
treated as equal in importance to measured latency for AI-assisted flows.

**Reasoning.** Our defining workflow (`05`) takes seconds to minutes. No amount
of optimization makes BRD synthesis instant — the latency is inherent to the
model call. What determines whether the product feels fast or broken is
therefore not duration but **feedback**.

This is a genuine engineering concern with architectural consequences, not a
design garnish: streaming, progress reporting and optimistic updates all impose
requirements on the realtime gateway, the job model and the frontend's state
handling.

**Techniques, in order of impact for our workload:**

| Technique | Where it applies |
| --- | --- |
| **Streaming output** | AI generation — first token under 3s (P-6) transforms a 90-second wait into a readable, progressing document |
| **Meaningful progress** | Long jobs — "analyzing 12 discovery notes" beats a spinner by a wide margin |
| **Optimistic updates** | Approvals, edits, status changes — instant feedback, reconciled on confirmation |
| **Skeleton screens** | Initial loads — communicates structure before content |
| **Prefetching on intent** | Hover and navigation prediction for likely next views |
| **Virtualization** | Large lists and graph views — render what is visible |

**A 90-second operation that streams and reports progress is experienced as
faster than a 20-second operation that shows a spinner.** This is not a
rationalization for being slow; it is a statement about where engineering effort
produces user-visible return once the inherent latency cannot be reduced.

**Alternatives.** *Reduce actual latency only* — the purist position; hits a hard
floor set by the model provider, beyond which no engineering helps. *Spinner and
wait* — cheapest to build, and makes a 90-second operation indistinguishable from
a hung one, which is the specific impression that drives abandonment. *Fully
background the work and notify later* — appropriate for genuine batch operations,
and wrong for the interactive authoring loop that is our core workflow.

**Trade-offs.** Streaming and optimistic updates add real complexity: partial
state, reconciliation, error handling on a half-rendered result. Optimistic
updates that must be rolled back are worse than no optimism at all, so they are
used only where failure is genuinely rare.

**Benefits.** The product feels responsive despite inherent latency we cannot
remove. Streaming also lets users start reading and evaluating output before it
completes, which shortens the real end-to-end task time, not merely the
perception of it.

**Long-term impact.** These patterns are hard to retrofit — they shape the API
contract and the client state model. Designing for them from Phase 1 is far
cheaper than adding them in Phase 3.

---

## Caching Discipline

**Decision.** Caching is a late-stage optimization, applied after the underlying
operation has been made efficient — never as the first response to slowness.

**Reasoning.** A cache in front of an inefficient operation hides the
inefficiency while adding a correctness liability: staleness, invalidation
complexity, and a cold-cache performance cliff that appears at the worst moment
(after a deploy, during an incident, exactly when load is highest). The
underlying operation is still slow; it is now slow *and* obscured.

Caching also introduces a security surface. A cache key missing its tenant
component is a direct cross-tenant leak (D-58), and a TTL-only cache of
permissions serves revoked access until it expires (D-71).

**Order of preference:**

1. Make the operation efficient — indexes, query shape, fewer round trips.
2. Reduce how often it is needed — batching, better client design.
3. Cache, with an event-driven invalidation strategy.
4. Cache with TTL only, where staleness is genuinely acceptable and non-security-relevant.

**Trade-offs.** Steps 1 and 2 take longer than step 3. Caching first is a real
short-term temptation and a reliable long-term cost.

**The exception: AI response caching** (D-72) is high-value and applied early,
because identical inputs genuinely produce identical outputs and the saving is
monetary as well as temporal. It is safe precisely because the key includes the
full input, prompt version, model and tenant — there is no staleness question.

---

## Regression Prevention

Performance decays silently unless it is defended continuously.

| Mechanism | Catches | When |
| --- | --- | --- |
| Endpoint benchmarks in CI | Direct latency regression on critical paths | Per PR |
| N+1 detection failing tests | The most common defect class | Per PR |
| Query plan checks on new queries | Missing indexes before production | Per PR |
| Bundle size budgets | Frontend weight creep | Per PR |
| Real user monitoring | Actual experience across devices and networks | Continuous |
| Continuous profiling in production | Gradual drift no benchmark covers | Continuous |
| Load tests at phase milestones | Behaviour under realistic concurrency | Per phase |
| Per-tenant latency telemetry (O-3) | Degradation concentrated in large tenants | Continuous |

**Per-tenant telemetry is the one most often missing and most valuable here.**
Aggregate latency can look healthy while the ten largest customers — the ones
whose churn matters most — experience something entirely different.

---

## When Not to Optimize

Stated explicitly, because the failure mode is as costly as the opposite.

| Situation | Correct action |
| --- | --- |
| Budget is met | Stop. There is no problem to solve. |
| No profile exists | Measure first. Always. |
| The code is not on a hot path | Leave it readable. |
| The gain is bounded below ~10% by Amdahl | Not worth the maintainability cost |
| Optimization would obscure domain logic | Move it out of the domain, or accept the cost |
| The requirement might change | Optimizing an unstable requirement is optimizing waste |
| It is cheaper to buy capacity | Compare engineering hours against infrastructure cost honestly |

**The last row deserves emphasis.** Engineering time is the scarcest resource we
have. A performance problem solvable by a larger database instance for a modest
monthly sum is frequently *not worth* a week of engineering — and senior
engineers systematically undervalue this trade because optimizing is more
satisfying than provisioning.

The counter-case: an inefficiency that scales with usage will eventually cost
more than fixing it, and per-request AI inefficiency compounds fastest of all.
The test is whether the cost grows with load or is fixed.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-255 | Every user-facing surface has a decomposed latency budget with explicit headroom | Global targets are unactionable; decomposition makes regressions attributable |
| D-256 | Optimization requires a profile; intuition-driven optimization is prohibited | Intuition consistently targets what we understand rather than what is slow |
| D-257 | Fix only the dominant cost, then re-measure | The dominant cost moves; second-order fixes are usually unnecessary |
| D-258 | p99 is a first-class target alongside p95 | The tail is concentrated in the largest customers |
| D-259 | Autoscale before ~70% utilization because queueing delay is non-linear | Latency collapses sharply past that point, before CPU looks alarming |
| D-260 | Perceived performance engineered deliberately for AI flows | Inherent latency cannot be removed; feedback determines the experience |
| D-261 | Streaming and progress reporting are architectural requirements, not polish | They constrain the API contract and client state model; retrofitting is expensive |
| D-262 | Caching is applied after the operation is efficient, never instead | A cache over an inefficient operation adds a correctness liability and a cold-start cliff |
| D-263 | AI response caching is the deliberate exception, applied early | Identical inputs, identical outputs, and the saving is monetary |
| D-264 | Per-tenant latency telemetry is required, not aggregate only | Aggregate health can hide a broken experience for the largest customers |
| D-265 | Buying capacity is compared honestly against engineering time | Engineers systematically undervalue this trade |
| D-266 | Performance beyond budget is not pursued | Optimizing past the requirement costs maintainability for no asked-for benefit |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Budgets set once and never revised against reality | Ignored, then abandoned | Reviewed at phase boundaries against measured baselines |
| RLS policy evaluation degrades hot queries | P-1 breached by the isolation mechanism itself | Benchmarked with policies enabled from Phase 0; tenant-leading composite indexes |
| Graph traversal fails P-3 at real volume (TR-2) | The most differentiating feature feels broken | Phase 0 benchmark; materialized paths or a derived read model as the fallback |
| Tail latency degrades silently as tenant data grows | Largest customers churn without a monitoring signal | Per-tenant p99 telemetry; alerting on distribution, not average |
| Premature optimization degrades readability | Maintainability cost with no measured benefit | Profile-first rule; review challenges unmeasured optimization |
| Perceived-performance patterns deferred to a later phase | Expensive retrofit into API and client state | Treated as Phase 1 architectural requirements |
| Caching used to mask an inefficiency | Cold-start cliff during incidents; correctness risk | Explicit order of preference; caching decisions reviewed |

## Dependencies

- **Depends on:** NFR targets (`04`), architecture (`05`), scalability (`08`),
  AI strategy (`10`), principles (`22`).
- **Depended on by:** testing strategy (`14`, benchmarks), deployment (`15`,
  telemetry), quality gates (`16`).

## Future Improvements

- Establish measured baselines in Phase 1 and re-derive every budget from data;
  the decomposition above is an informed estimate, not a measurement.
- Add continuous production profiling once traffic justifies the overhead.
- Publish per-endpoint budgets alongside each endpoint's contract, so the budget
  lives with the thing it constrains.
