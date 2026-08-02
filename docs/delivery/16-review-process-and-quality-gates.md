# Review Process and Quality Gates

## Purpose

Define how changes are reviewed and what must be true before code merges and
before it reaches production. Gates are where the charter's standards stop being
aspirations and become conditions.

## Scope

**In scope:** review process and expectations, reviewer responsibilities, review
checklists, architectural decision records, and the automated gates at each
stage.

**Out of scope:** the standards themselves (`13`), test content (`14`), pipeline
mechanics (`15`).

---

## Review Philosophy

**Review is for what tools cannot check.** Formatting, types, complexity limits,
boundary violations and security patterns are all automated (`13`). What remains
is judgment — and judgment is expensive, so it is protected from noise.

Reviewers evaluate:

| Question | Why a human is required |
| --- | --- |
| Is this solving the right problem? | Tools have no concept of intent |
| Is the design appropriate for the complexity? | Over- and under-engineering are both invisible to tooling |
| Are the abstractions well chosen? | Naming and conceptual fit are judgment |
| Does it fit the architecture and domain model? | Especially critical for AI-generated code (D-136) |
| Are the tests testing meaningful behaviour? | Coverage tools count lines, not value |
| What breaks in production? | Requires operational experience |
| Would a new engineer understand this in six months? | The only real readability test |

**Explicitly not a reviewer's job:** style, formatting, import order, or
anything a tool decides (D-126). Such comments crowd out substantive review and
train authors to skim feedback.

---

## Review Process

### Author responsibilities

The author does the work that makes review cheap:

- **Small, focused PRs** — under ~400 lines (D-121). Beyond that, review quality
  collapses into approval.
- **Self-review first.** Read your own diff before requesting review; it catches
  a meaningful share of issues at zero cost to anyone else.
- **A description that explains *why***, what approach was taken and what
  alternatives were rejected. The diff shows what changed; only the author knows
  why.
- **All automated gates green before requesting review.** Requesting review on a
  red build wastes reviewer time.
- **Call out uncertainty explicitly** — "I'm unsure about this approach" directs
  attention where it is most valuable.
- **Note the AI-assisted portions** and confirm they have been understood, not
  merely accepted. The author owns the code regardless of who wrote it (D-136).

### Reviewer responsibilities

- **Respond within one working day.** A stale PR blocks the author and grows
  merge conflicts; review latency is a team-wide velocity tax.
- **Understand before commenting.** Read the linked requirement and the whole
  diff, not just the changed lines.
- **Distinguish blocking from non-blocking.** Prefix non-blocking observations
  clearly ("nit:", "consider:"). An unlabelled preference reads as a demand and
  causes needless rework.
- **Explain reasoning, and propose alternatives.** "This is wrong" is not
  review; "this breaks under concurrent requests because X — consider Y" is.
- **Approve when it is good enough**, not when it is what you would have
  written. Perfect-as-enemy-of-good in review is a real and costly failure mode.
- **Say so when you are unqualified** on part of a change, and request another
  reviewer rather than rubber-stamping.

### Requirements

| Change type | Reviewers | Additional |
| --- | --- | --- |
| Standard | 1 | — |
| Security-sensitive (auth, tenancy, external access) | 2 | Security checklist |
| Architecturally significant | 2 | **ADR merged first** |
| Database schema | 2 | Migration checklist; production-scale test |
| AI workflow or prompt | 2 | Evaluation results attached |
| Infrastructure | 2 | Plan output attached |
| Dependency addition | 1 | Justification: maintenance, weight, licence |

**Two reviewers where a mistake is expensive, one where it is not.** Requiring
two on everything halves throughput for marginal benefit on routine changes;
requiring one on tenancy changes is negligent. The distinction is deliberate.

---

## Review Checklists

Applied by change type, not universally — a universal checklist gets skimmed and
becomes theatre.

### Every change

- Requirement traced; scope matches the requirement (no unrelated changes)
- Errors handled; failure modes considered
- Tests assert meaningful behaviour, including failure cases
- No duplicated logic, dead code, TODOs or placeholders (charter absolutes)
- Naming reflects domain language
- Comments explain why, not what

### Security-sensitive

- Authorization enforced and tested, including the negative case
- Tenant scoping applied and covered by isolation tests
- Input validated at the boundary; no injection surface
- No secrets, no sensitive data in logs
- Audit events emitted where required (SEC-5)
- Rate limiting considered

### Database

- Expand-contract followed (D-120)
- Backward compatible for the rollout window
- Indexes present for new query patterns, leading with `tenant_id`
- RLS policy defined for tenant-scoped tables
- Backfill batched and monitored, not blocking
- Tested against production-scale data

### AI workflow

- Prompt versioned in the repository (D-93)
- Output schema-constrained (D-52)
- Retrieval tenant-scoped before ranking (D-23)
- Untrusted content isolated (SEC-9)
- Cost profile understood; tier justified
- Evaluation results attached; no regression
- Human approval gate preserved (D-36)

### Performance-sensitive

- No N+1 patterns
- Caching considered with a defined invalidation strategy
- Long operations asynchronous (P-9)
- Pagination on collections

---

## Architectural Decision Records

Significant decisions are recorded as ADRs in `docs/architecture/adr/`, using
the template there.

**An ADR is required when a change:**

- Introduces or removes a technology, service or major dependency
- Changes a module boundary or the Delivery Graph kernel
- Changes the tenancy, isolation or authorization model
- Changes the AI provider abstraction or evaluation approach
- Extracts a service (D-39, with evidence the trigger was met)
- Reverses a decision recorded in this foundation set

**The ADR merges before the implementation.** Reviewing a design after it is
built produces rationalization rather than evaluation — the code exists, the
effort is sunk, and the review becomes a formality.

**Why ADRs matter for this product specifically:** we are building a platform
whose entire premise is that decisions should carry their rationale. Not
practising that in our own engineering would be incoherent.

---

## Quality Gates

Gates are automated and blocking. **A gate that can be bypassed by choice is not
a gate.** Emergency bypass exists but requires two approvals and produces an
automatic follow-up defect.

### Gate 1 — Pre-commit (local, seconds)

Formatting, fast lint, secret detection. Fast by design; a slow hook gets
bypassed and stops protecting anything.

### Gate 2 — Pull request (blocking merge)

| Check | Threshold |
| --- | --- |
| Format and lint | Zero violations |
| Static analysis | Zero errors at max strictness (M-1) |
| Architecture and boundary tests | Zero violations (M-2) |
| Unit tests | All pass; domain coverage > 85% (M-3) |
| Integration tests | All pass |
| Feature / API tests | All pass, including negative authorization |
| **Tenant isolation suite** | All pass (T-1, T-7) |
| Contract verification | Conformant |
| Secret scanning | Zero findings (SEC-3) |
| SAST | Zero critical/high |
| SCA | Zero critical/high (SEC-7) |
| IaC and container scanning | Zero critical/high |
| Adversarial AI suite | All pass (SEC-9) |
| Bundle size | Within budget (P-4) |
| Pipeline duration | < 10 min (M-4) |
| Human approval | Per change type above |

### Gate 3 — Pre-production promotion

| Check | Threshold |
| --- | --- |
| Staging deploy successful | Healthy |
| E2E critical journeys | All pass |
| Smoke tests | All pass |
| Migration verification | Applied cleanly |
| No open critical incidents | Confirmed |
| Error budget not exhausted | Within budget (D-28) |

### Gate 4 — Post-deployment verification

| Check | Action on breach |
| --- | --- |
| Health checks | Automatic rollback |
| Error rate within budget | Automatic rollback |
| Latency within SLO | Automatic rollback |
| No new critical alerts | Investigate |

### Gate 5 — Scheduled

| Check | Frequency | Action |
| --- | --- | --- |
| Full AI evaluation | Nightly + on AI changes | Block release on regression (D-96) |
| Load tests | Per phase milestone | Block phase completion |
| Dependency scan | Daily | Critical patched within 72h (SEC-8) |
| Restore drill | Quarterly | Fix procedure on failure |
| Access review | Quarterly | Revoke unnecessary access |
| Penetration test | Annual | Remediate findings (SEC-10) |

---

## Emergency Bypass

Production incidents sometimes require a fix faster than the full pipeline.

- Security gates (secret scanning, isolation suite, SAST) are **never**
  bypassable. The reason a change is urgent is never a reason isolation may
  break.
- Other gates may be bypassed with two approvals, a recorded reason, and an
  automatically created follow-up defect.
- Every bypass is reviewed in the post-incident review.
- **Bypass frequency is tracked.** Frequent bypass means the gates are wrong or
  the incident rate is too high — either is a problem to solve, not to normalize.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-162 | Review covers judgment only; tools own the rest | Protects scarce reviewer attention |
| D-163 | Two reviewers for security, schema, architecture, AI and infrastructure | Matches review cost to mistake cost |
| D-164 | ADR merges before implementation | Post-hoc review produces rationalization, not evaluation |
| D-165 | Checklists are per change type, not universal | Universal checklists get skimmed |
| D-166 | Non-blocking comments must be explicitly labelled | Unlabelled preferences read as demands |
| D-167 | Review response expected within one working day | Review latency is a team-wide velocity tax |
| D-168 | Authors self-review and explain *why* before requesting review | Cheapest possible defect detection |
| D-169 | Security gates are never bypassable | Urgency is not a reason isolation may break |
| D-170 | Bypasses require two approvals, a reason and a follow-up defect | Makes the exception visible and self-correcting |
| D-171 | Bypass frequency tracked as a health metric | Frequent bypass means the gates or the process is wrong |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Review becomes rubber-stamping under delivery pressure | Defects reach production; the process becomes theatre | Small PRs; automated gates carry the mechanical load; escaped-defect review |
| Gates block delivery for the wrong reasons | Pressure to weaken or bypass them | Gate failures reviewed for false positives; tuned rather than removed |
| Review latency blocks the team | Velocity loss; larger batches | One-day expectation tracked; PR age visible |
| Two-reviewer requirement is impractical at current team size | Bottleneck or silent non-compliance | Honest constraint: with a very small team, external review may be required for the highest-risk categories |
| Over-strict review discourages contribution | Morale and velocity | "Good enough, not what I would have written" is an explicit norm |
| ADR process treated as bureaucracy and skipped | Decisions lose their rationale — the exact failure the product exists to fix | ADRs kept short; required only for genuinely significant decisions |

## Dependencies

- **Depends on:** coding standards (`13`), testing strategy (`14`), deployment
  strategy (`15`), security strategy (`09`).
- **Depended on by:** definition of done.

## Future Improvements

- Track escaped defects to identify which review categories are underperforming
  and adjust checklists accordingly.
- Add automatic reviewer routing by module ownership as the team grows.
- Measure review latency and PR size as explicit process health metrics.
