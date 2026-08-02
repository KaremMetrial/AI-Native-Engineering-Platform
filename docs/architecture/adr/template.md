# ADR-nnnn: <Short decision title>

- **Status:** Proposed
- **Date:** YYYY-MM-DD
- **Deciders:** <names>
- **Supersedes:** <ADR-nnnn, or none>
- **Related:** <D-nn decisions or documents this affects>

## Context

What situation forces a decision? State the problem, the constraints, and the
requirements that apply — reference the specific NFR or business goal IDs rather
than describing them again.

Include what is *not* known. A decision made under uncertainty is fine; a
decision that hides its uncertainty is not, because the next reader cannot tell
which parts were confident.

## Decision

What we are doing, stated plainly in one or two sentences.

## Reasoning

Why this option. Address, explicitly:

- **Why:** what makes this the right choice against the constraints above
- **Why not the alternatives:** each option considered and the specific reason it
  was not chosen (see the table below)
- **Future scalability:** how this behaves as the system grows
- **Maintenance cost:** ongoing engineering burden
- **Operational cost:** infrastructure, licensing, on-call
- **Migration risk:** how expensive is this to reverse, and what would force us
  to
- **Business impact:** what this enables or constrains commercially

## Alternatives considered

| Option | Strengths | Why not chosen |
| --- | --- | --- |
| | | |

An ADR with no alternatives is a record of a decision that was never really
made. If there genuinely was only one viable option, say so and explain what
ruled the others out before they were seriously evaluated.

## Consequences

**Positive:** what improves.

**Negative:** what gets worse. **Every decision has a downside** — an ADR listing
only benefits is advocacy, not a decision record, and it leaves the next reader
unable to judge whether the trade was still worth it.

**Neutral:** what changes without being better or worse.

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| | | |

## Dependencies

What must be true, built, or decided for this to hold. What else changes as a
result.

## Future Improvements

Known gaps, deferred work, and the conditions under which this decision should
be revisited.
