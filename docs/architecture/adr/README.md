# Architectural Decision Records

## What lives here

One record per architecturally significant decision made **from the start of
implementation onward**.

The decisions taken while establishing the engineering foundation are recorded
in the foundation documents themselves, numbered `D-01` through `D-209`. Those
documents *are* the record of the initial architecture; duplicating them as
ADRs would create two sources of truth for the same decisions, which the charter
forbids.

From Phase 0 onward, new significant decisions — and any reversal of a `D-nn`
decision — are recorded here.

## When an ADR is required

Per `docs/delivery/16-review-process-and-quality-gates.md`, a change requires an
ADR when it:

- Introduces or removes a technology, service, or major dependency
- Changes a module boundary or the Delivery Graph kernel
- Changes the tenancy, isolation, or authorization model
- Changes the AI provider abstraction or evaluation approach
- Extracts a service (requires evidence the extraction trigger was met)
- Reverses a decision recorded in the foundation set

When unsure, write one. A short ADR that turns out to have been unnecessary
costs twenty minutes. A significant decision with no record costs whoever
inherits it days of archaeology.

## Rules

1. **The ADR merges before the implementation.** Reviewing a design after it is
   built produces rationalization, not evaluation.
2. **An accepted ADR is immutable.** Superseding it means writing a new ADR and
   marking the old one `Superseded by ADR-nnnn`. Never edit the reasoning of an
   accepted record — its value is the account of what was known and believed at
   the time.
3. **Two reviewers**, as for any architecturally significant change.
4. **Number sequentially**, zero-padded: `0001-short-kebab-title.md`.
5. **Keep it short.** One to two pages. An ADR nobody reads because it is long
   provides no more value than no ADR at all.

## Status values

| Status | Meaning |
| --- | --- |
| `Proposed` | Under review; not yet agreed |
| `Accepted` | Agreed and in force |
| `Superseded by ADR-nnnn` | Replaced; retained for the historical record |
| `Rejected` | Considered and declined — kept, because knowing what was rejected and why prevents relitigating it |

`Rejected` records are deliberately retained. A rejected option that leaves no
trace gets proposed again every six months.

## Index

| ADR | Title | Status |
| --- | --- | --- |
| ADR-0001 *(no file yet)* | `metrial-auth` evaluation outcome | **Pending** — blocked on the Phase 0 evaluation spike (D-20, D-195); cannot be written honestly until that investigation actually happens |
| [ADR-0002](0002-genuine-multi-provider-ai-support.md) | Genuine multi-provider AI support | Accepted — amends D-51 |
| [ADR-0003](0003-bounded-agent-steps-within-workflows.md) | Bounded agent steps within workflows | Accepted — amends D-89 |

ADR-0001 has no file yet: writing it now would mean fabricating findings about
a real package we have not evaluated, which the charter's prohibition on faked
implementations extends to faked decision records. It is created once the
Phase 0 spike (`docs/governance/20-roadmap.md`) actually runs.

ADR-0002 and ADR-0003 were writable immediately because the decisions they
record — and their full reasoning, alternatives and risks — already existed in
`docs/architecture/ai/50-ai-platform-and-multi-provider.md` and
`docs/architecture/ai/56-workflow-and-agent-engine.md` respectively. Writing
them was extracting an existing, fully-argued decision into the formal,
immutable record the process requires — not originating a new one.

New records are added to this index as they are accepted.

## Template

Copy `template.md` to start a new record.
