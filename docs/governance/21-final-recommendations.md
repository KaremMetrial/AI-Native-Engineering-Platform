# Final Engineering Recommendations

## Purpose

State, as technical leadership, what matters most in this foundation: the
decisions that carry the most weight, the assumptions that must be validated
before they are built upon, the ways this plan most plausibly fails, and what
happens next.

## Scope

**In scope:** the load-bearing decisions, unvalidated assumptions, honest
assessment of the plan's weaknesses, non-negotiables, and immediate next steps.

**Out of scope:** re-stating what the preceding twenty documents already
establish.

---

## The Ten Decisions That Matter Most

If everything else here were rewritten, these would remain. They are ordered by
the cost of getting them wrong.

### 1. Tenant isolation is enforced by the database, not by discipline (D-54)

The only failure class that can end the company. Every isolation model relying
solely on application-level filtering eventually fails — one forgotten clause in
one query. With RLS, a query missing its tenant scope returns zero rows: the
failure mode becomes a visible bug rather than a silent breach. **This single
decision does more for the platform's survival than any other.**

### 2. The Delivery Graph is the product (D-01, D-13)

AI generation is commoditizing. The traceable, tenant-owned chain of *why* is
not. Every architectural decision that costs us velocity — traceability from day
one, immutable versioning, lineage on every artifact — is buying this asset. If
the graph is abandoned as too costly, the platform becomes a document generator
competing on price.

### 3. AI produces drafts; humans confer authority (D-36, D-88)

Requirements and estimates become contracts. A system where AI output is
authoritative without review is a system that eventually helps someone sign a
commitment they cannot deliver. This decision will face pressure — full
automation demos better — and it should be defended, because it is
simultaneously the trust mechanism, the injection containment (D-81), and the
regulatory alignment (BR-6).

### 4. No AI inference in a synchronous request path (D-21, D-35)

One constraint that dictates the job architecture, the realtime gateway, the
frontend's state model, and our availability independence from third parties.
Violating it exhausts worker pools under trivial load and couples our uptime to
a provider's. Enforced mechanically because it will otherwise be violated by
accident.

### 5. Modular monolith, with AI orchestration extracted (D-29, D-30)

Our module boundaries are hypotheses; some are wrong. Inside a monolith,
correcting one is a refactor. Across services, it is a distributed migration.
The AI service is extracted because it genuinely differs on runtime, scaling,
failure profile and change cadence — the standard for extraction, which no other
component currently meets.

### 6. Standards are mechanically enforced, at maximum strictness, from commit one (D-125, D-127)

Every unenforced standard decays, and every team believes theirs will not. This
matters more than usual here: with AI-assisted contribution, code arrives faster
than humans can carefully review it, and automated gates are what scale review
capacity.

### 7. Evaluation gates AI quality, on aggregate thresholds (D-96)

Without evaluation, AI quality is unknown and regressions are invisible. With
per-run pass/fail on non-deterministic output, tests are flaky and get ignored.
Aggregate regression thresholds give a stable signal about an unstable system.

### 8. AI cost is metered per tenant from Phase 1 (D-101)

In an AI product, unit economics is an architectural property, not a finance
report. Unmetered inference can invert gross margin on the most engaged
customers — meaning growth destroys the company. Measurement must precede
monetization.

### 9. Trunk-based development with expand-contract migrations (D-111, D-120)

Small changes are safer changes, and everything else in the delivery strategy
depends on this. Expand-contract is what makes rolling deploys safe; it is the
practice most often skipped and the one whose absence causes the most
deploy-time incidents.

### 10. Every phase ships standalone value (D-197)

Protects against the most common failure of ambitious platforms: eighteen months
of foundation-building before anyone can buy anything.

---

## Assumptions That Must Be Validated

**These are beliefs, not facts.** Building on them without validation is the
most expensive mistake available, which is why Phase 0 exists.

| Assumption | Risk if wrong | Validation |
| --- | --- | --- |
| **`metrial-auth` composes with our tenancy model** | TR-3 — months lost; a primary justification for the Laravel choice weakens materially | Phase 0 spike + ADR-0001, before any identity code |
| **Postgres meets P-3 for graph traversal at realistic volume** | TR-2 — the most differentiating feature feels broken | Phase 0 benchmark on synthetic volume |
| **AI output quality can reach the G4 threshold** | CR-2 — the product has negative value | Phase 1 evaluation against golden sets and real design partners |
| **Users will maintain graph links as a by-product of work** | BR-1 — the strategic bet is invalidated | G3 measured from Phase 1; developer adoption gated in Phase 3 |
| **Agencies will pay for pre-sales acceleration** | BR-3 — beachhead too small | Phase 1 design partners; Phase 2 paying customers |
| **AI cost per artifact stays within margin** | CR-3 — unit economics inverted | Metered from Phase 1, reconciled monthly |

**The honest framing:** this foundation is a well-reasoned hypothesis, not a
proof. Its value lies in being explicit enough that each assumption can be
tested and each decision revisited with evidence — which is precisely what the
ADR process and phase exit criteria exist to enable.

---

## Where This Plan Is Most Likely to Fail

Leadership's job includes naming the weaknesses in its own plan.

### The quality bar may exceed team capacity (ER-3)

The standards here — maximum-strictness analysis, two reviewers on sensitive
changes, comprehensive gating, a demanding Definition of Done — are correct for
the platform being built. Whether they are *affordable* depends entirely on team
size, and the two-reviewer requirement is impractical below roughly four
engineers.

**Recommendation:** automate relentlessly so gates carry the mechanical load,
and if a manual practice must be relaxed, **relax it explicitly and record it**.
Silent erosion is the failure mode; an honest, recorded exception is not.

### The graph may be a burden users route around (BR-1)

The entire strategy rests on users maintaining traceability. If link creation
feels like data entry, they will not do it, and the moat will not exist.

**Recommendation:** treat "links are created as a by-product of doing the work,
never as a separate chore" as a product design constraint with the same standing
as any architectural rule. Measure G3 from the first week of Phase 1 and treat a
low number as a design emergency rather than an adoption problem.

### Scope is enormous relative to any plausible team

Fifteen bounded contexts, four phases, a full software delivery lifecycle. The
risk is not that any single piece is wrong — it is that the whole is too much
and everything ends up half-built.

**Recommendation:** hold the phase discipline absolutely. Phase 1 is
requirements only. The temptation to add "just estimation" to Phase 1 because a
prospect asked is how ambitious platforms become uniformly shallow.

### AI quality may plateau below the usefulness threshold (CR-2)

Generating a genuinely good BRD from incomplete discovery input is hard, and the
failure is subtle: plausible output that is wrong in ways a busy reviewer misses.

**Recommendation:** if evaluation shows a workflow cannot meet its threshold,
**cut it rather than ship it degraded.** A narrow set of excellent AI
capabilities plus outstanding traceability is a strong product. A broad set of
mediocre ones is a product that erodes trust with every use — and trust, once
lost on AI output, does not come back.

---

## Non-Negotiables

Everything in this foundation is open to revision with evidence, except these.
They are non-negotiable because each is either unrecoverable when breached or
foundational to everything else.

1. **Tenant isolation.** No exception, no deadline, no bypass. Security gates
   are never bypassable (D-169).
2. **Human approval before an AI artifact becomes authoritative.**
3. **No secrets in source; no sensitive data in logs.**
4. **The Definition of Done does not flex; scope flexes** (D-177).
5. **Charter absolutes** — no TODOs, no placeholders, no dead code, no ignored
   analysis, no untestable code.
6. **Every architecturally significant decision gets an ADR, before
   implementation** (D-164).

---

## What Happens Next

1. **Review and approve this foundation.** It is a set of decisions requiring
   agreement, not a document requiring sign-off. Disagreements are best raised
   now, while changing course costs a conversation.
2. **Execute Phase 0.** Three spikes — identity, isolation, graph performance —
   plus the scaffold and gates. This is the highest-value work available, and
   the cheapest information we will ever buy.
3. **Record outcomes as ADRs.** ADR-0001 (identity) is the first, and it should
   exist before any identity code does.
4. **Recruit design partners in parallel.** Phase 1's exit criteria require
   three of them; sourcing takes longer than building, and starting late makes
   it the schedule constraint.
5. **Revisit this set at each phase boundary.** It is versioned; it is expected
   to change. A foundation that never changes is one that stopped being consulted.

---

## Closing Assessment

**What is strong here:** the isolation model is defensible against the failure
that matters most. The architecture is appropriately sized — genuinely modular
without paying distributed-systems costs prematurely. The AI strategy takes
grounding, evaluation and cost seriously rather than treating a model call as
the whole product. The phase sequencing retires risk before spending on it. And
the Delivery Graph is a genuine strategic asset rather than a feature that
sounds strategic.

**What is uncertain:** whether AI output reaches the quality threshold that
makes any of this valuable, whether users maintain the graph, and whether the
team can sustain this standard. None of these can be resolved by better
planning. They are resolved by building the smallest thing that tests them,
which is exactly what Phase 0 and Phase 1 are designed to do.

**The recommendation:** proceed. Execute Phase 0 without shortcuts, treat its
findings as authoritative even when inconvenient, and hold the phase discipline
that keeps this ambitious rather than overextended.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-205 | Six non-negotiables; everything else revisable with evidence | Distinguishes principles from preferences, so pressure lands on the right things |
| D-206 | Relaxed practices are recorded explicitly, never eroded silently | Honest exceptions are recoverable; silent decay is not |
| D-207 | Cut an AI workflow rather than ship it below its threshold | Broad mediocrity erodes trust permanently; narrow excellence does not |
| D-208 | Foundation reviewed and amended at every phase boundary | A foundation that never changes has stopped being consulted |
| D-209 | Design partner recruitment starts in parallel with Phase 0 | Sourcing is slower than building; starting late makes it the constraint |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Foundation treated as immutable doctrine | Wrong decisions defended past their evidence | Explicitly versioned; ADRs supersede; phase-boundary review |
| Foundation ignored once delivery pressure begins | Standards erode; the effort is wasted | Mechanically enforced where possible; `CLAUDE.md` carries it into every AI session |
| Phase 0 compressed or skipped | Unvalidated assumptions fail late, on committed schema | Framed as risk retirement with concrete exit criteria |
| Non-negotiables challenged during a crunch | Unrecoverable failures | Agreed in advance, in writing, so the debate happened while everyone was calm |

## Dependencies

- **Depends on:** the entire foundation set.
- **Depended on by:** the decision to proceed.

## Future Improvements

- Revisit after Phase 0 with the spike results, and amend the decisions those
  results contradict.
- Re-assess the risk register with evidence after Phase 1.
- Add calendar estimates once team size is fixed.
