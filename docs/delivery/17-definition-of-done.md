# Definition of Done

## Purpose

Establish one unambiguous, shared standard for what "done" means, so that
completion is a verifiable state rather than an opinion. Ambiguous done-ness is
how work is reported complete, then reopened three weeks later as a defect.

## Scope

**In scope:** definitions of done for a task, a feature, a phase, and an AI
capability; and the explicit exclusions that keep the definition honest.

**Out of scope:** the gates that verify these conditions (`16`) and the
practices that produce them (`12`–`15`).

---

## Principle

**Done means "in production, working, observable, and maintainable by someone
else."**

Not "code written." Not "tests pass locally." Not "merged." Each of those is a
milestone toward done, and treating any of them as done is how the gap between
reported and actual progress opens.

Three qualities distinguish this definition:

- **Verifiable** — every criterion is objectively checkable, not a judgment call.
- **Non-negotiable** — the definition is not relaxed under deadline pressure.
  A definition that flexes under pressure is a definition that describes nothing.
- **Complete** — it covers the operational and documentation obligations that
  are otherwise silently deferred and never paid.

---

## Definition of Done — Task

A single unit of work within a feature.

### Implementation

- [ ] Requirement understood and traced to its source artifact (G3)
- [ ] Implemented per the architecture, in the correct module and layer
- [ ] No duplicated logic, dead code, TODOs, placeholders, or commented-out code
- [ ] Errors handled; failure modes considered and addressed
- [ ] No compiler or analyzer warnings introduced or suppressed without
      justification

### Testing

- [ ] Unit tests for domain logic, asserting behaviour including failure cases
- [ ] Integration tests where infrastructure is touched
- [ ] API tests including the negative authorization case
- [ ] Tenant isolation covered where data access is added or changed
- [ ] All tests pass in CI; no test skipped or quarantined to achieve green

### Quality

- [ ] All PR gates green (`16`, Gate 2)
- [ ] Code reviewed and approved per change type
- [ ] Naming reflects domain language
- [ ] Complexity within limits; no function requiring a paragraph to explain

### Security

- [ ] Authorization enforced and tested
- [ ] Tenant scoping applied
- [ ] Input validated at the boundary
- [ ] No secrets; no sensitive data in logs
- [ ] Audit events emitted where required

### Integration

- [ ] Merged to `main`; `main` remains releasable
- [ ] Deployed to staging and verified
- [ ] No regression in existing functionality

---

## Definition of Done — Feature

A user-visible capability, comprising multiple tasks. Everything in Task DoD,
plus:

### Functional

- [ ] All acceptance criteria met and demonstrated
- [ ] Edge cases and error states handled with sensible user-facing behaviour
- [ ] Empty, loading and error states implemented — **a feature without these is
      not finished**, it merely works on the happy path
- [ ] Works across supported browsers and viewports (U-3)

### Non-functional

- [ ] Latency within P-1/P-2 for affected endpoints
- [ ] No N+1 or unindexed query patterns introduced
- [ ] Accessible: keyboard navigable, screen-reader usable, WCAG 2.2 AA (U-1/U-2)
- [ ] Behaves correctly under concurrent multi-tenant load
- [ ] Long operations asynchronous with progress feedback (P-9)

### Operational

- [ ] Instrumented: traces, structured logs, metrics — all tenant-attributed (O-3)
- [ ] Alerts defined where failure is user-impacting, each with a runbook
- [ ] Feature flag in place, with owner and removal date (D-118)
- [ ] Kill switch where the feature calls AI or an external service (D-119)
- [ ] Cost impact understood — AI, storage, compute

### Documentation

- [ ] API contract updated in `packages/contracts`
- [ ] User-facing documentation written where behaviour is not self-evident
- [ ] ADR merged if an architecturally significant decision was made
- [ ] Runbook entries for new operational concerns

### Release

- [ ] Deployed to production
- [ ] Verified working in production (not merely deployed)
- [ ] Monitored through at least one business day without regression
- [ ] Flag enabled for the intended audience

**"Verified working in production" is the criterion most often skipped**, and
its absence is why teams discover on Monday that Friday's feature never worked.
Deployment is not verification.

---

## Definition of Done — Phase

A roadmap phase (`docs/governance/20-roadmap.md`). All features meet Feature
DoD, plus:

- [ ] Phase objective demonstrably achieved, measured against the success metric
      stated in the roadmap
- [ ] NFR targets binding at this phase verified by test, not assumed (`04`)
- [ ] Load and spike tests pass at phase-appropriate targets
- [ ] Security review completed for the phase's new surface
- [ ] Tenant isolation suite extended to cover all new access patterns
- [ ] AI evaluation thresholds met for all new workflows (`10`)
- [ ] Documentation set updated and consistent — no contradictions introduced
- [ ] Technical debt incurred during the phase recorded with carrying cost
- [ ] Operational readiness confirmed: runbooks, alerts, dashboards, on-call
- [ ] Retrospective held; actions tracked

---

## Definition of Done — AI Capability

AI workflows have failure modes conventional features do not, so they carry
additional criteria. Everything in Feature DoD, plus:

- [ ] Workflow defined with explicit inputs, output schema, and quality criteria
- [ ] Prompt versioned in the repository (D-93)
- [ ] Model tier selected and justified against evaluation, not assumption (D-94)
- [ ] Output schema-constrained and validated; invalid output fails cleanly (D-95)
- [ ] Retrieval tenant-scoped before ranking, verified by test (D-23)
- [ ] Grounding uses approved artifacts only (D-91)
- [ ] Golden set created with expert-reviewed reference outputs
- [ ] Evaluation thresholds defined and met; no regression against baseline
- [ ] Adversarial injection tests pass (SEC-9)
- [ ] Human approval gate present; AI output is a draft (D-36)
- [ ] Provenance and confidence surfaced in the UI (U-4)
- [ ] Output editable before approval; edits captured (U-5)
- [ ] Assumptions and gaps surfaced rather than silently invented (D-99)
- [ ] Cost per invocation measured and attributed (O-5, $-2)
- [ ] Graceful degradation verified when the provider is unavailable (A-5)
- [ ] Loop, recursion and length limits enforced

**An AI capability without a golden set and thresholds is not done** — it is
untested, because there is no other way to know whether it works. Shipping one
means its quality is unknown and its regressions will be invisible.

---

## What Done Does Not Mean

Stated explicitly, because these are the substitutions actually made under
pressure:

| Not done | Why |
| --- | --- |
| "Code is written" | Untested and unreviewed code has unknown correctness |
| "It works on my machine" | Environment-specific success proves nothing |
| "Tests pass locally" | CI is the authority; local state is unverifiable |
| "It's merged" | Merged code that fails in production is not delivered |
| "It's deployed" | Deployed ≠ working; verification is a separate step |
| "The happy path works" | Error and edge states are most of real usage |
| "We'll add tests later" | Later does not arrive; the charter forbids it |
| "We'll document it later" | Same |
| "The AI output looked good" | Not evaluation (`10`) |
| "It's behind a flag so it doesn't matter" | Flagged code still ships, still runs, still has to be correct |

---

## Handling Genuine Deadline Pressure

The definition does not flex — but scope does. When a deadline cannot be met,
the honest options are:

1. **Reduce scope** — ship fewer capabilities, each fully done.
2. **Move the deadline.**
3. **Add capacity** (rarely effective in the short term).

**Not an option: shipping partial work and calling it done.** That converts a
schedule problem into a quality problem plus a schedule problem, and the debt is
repaid with interest by whoever inherits it.

This is stated in the foundation deliberately, because the pressure to relax the
definition always arrives, always arrives with a good reason, and is best
answered by a rule agreed in advance rather than a debate held while everyone is
tired.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-172 | Done means in production, working, observable and maintainable | Any weaker definition hides the gap between reported and actual progress |
| D-173 | Separate definitions for task, feature, phase and AI capability | Different scopes have genuinely different completion conditions |
| D-174 | "Verified working in production" is a distinct criterion from "deployed" | Deployment is not verification |
| D-175 | Empty, loading and error states are completion criteria | A happy-path-only feature is not finished |
| D-176 | AI capabilities require a golden set and met thresholds | Otherwise quality is unknown and regressions invisible |
| D-177 | The definition never flexes; scope flexes instead | Relaxing it converts a schedule problem into a permanent quality problem |
| D-178 | Operational readiness (telemetry, alerts, runbooks, kill switches) is part of done | Otherwise it is deferred indefinitely and paid for during an incident |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Definition perceived as too heavy; quietly ignored | Standards erode invisibly | Most criteria are automated in Gate 2; the manual list is short by design |
| Manual criteria checked superficially | False confidence | Automate progressively; verify in retrospectives |
| Pressure to redefine done during a crunch | Quality debt with compounding interest | D-177 agreed in advance; scope is the flexible variable |
| AI capability criteria slow AI delivery | Fewer AI features shipped | Accepted deliberately — an unevaluated AI feature that produces bad output is worse than no feature (G4) |
| Documentation criteria skipped as "not real work" | Knowledge loss; onboarding cost | Documentation is part of the PR, reviewed with the code |

## Dependencies

- **Depends on:** NFRs (`04`), AI strategy (`10`), development (`12`), coding
  standards (`13`), testing (`14`), deployment (`15`), review and gates (`16`).
- **Depended on by:** roadmap phase completion; all delivery planning.

## Future Improvements

- Automate more manual criteria — particularly instrumentation presence and
  documentation currency — so the checklist shrinks over time.
- Add a completion checklist template to the PR template so the criteria are in
  front of the author rather than in a document.
- Track reopened work as the primary signal of whether the definition is being
  applied honestly.
