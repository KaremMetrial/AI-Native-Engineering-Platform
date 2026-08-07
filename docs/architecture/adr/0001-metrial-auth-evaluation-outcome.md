# ADR-0001: `metrial-auth` Evaluation Outcome — Build Identity In-House

- **Status:** Accepted
- **Date:** 2026-08-07
- **Deciders:** Engineering leadership
- **Supersedes:** none
- **Related:** D-20, D-195, D-196, TR-3 (`docs/governance/19-risk-register.md`), `docs/product/03-core-modules-and-scope.md`, `docs/architecture/06-technology-decisions.md`, `docs/architecture/07-multi-tenancy-strategy.md`

## Context

D-20 required a Phase 0 spike evaluating `metrial-auth` — described in the
foundation documents as "an existing Laravel enterprise identity platform
in-house" covering SSO, SAML/OIDC, SCIM, WebAuthn, ABAC and adaptive MFA —
before any Identity module code is committed. `06` names this reuse as **the
single strongest argument** for choosing Laravel over alternatives, and
`docs/architecture/adr/README.md` explicitly held ADR-0001 open rather than
write it speculatively: "writing it now would mean fabricating findings about
a real package we have not evaluated."

This ADR exists because that investigation has now actually happened, and its
finding is negative on accessibility, not on the package's merits.

**What was checked, in order:**

1. **This development environment's package registries.** `composer search
   metrial-auth` and a direct query against Packagist's metadata endpoint
   (`repo.packagist.org/p2/metrial/metrial-auth.json`) both return nothing —
   no package by this name is publicly registered.
2. **Whether it might be a private repository this session could be granted
   access to.** No repository URL, vendor namespace, or registry endpoint for
   it exists anywhere in the 28-document foundation set — it is referenced by
   name only, everywhere it appears.
3. **The project owner directly**, since the foundation documents themselves
   couldn't resolve this: asked explicitly where the package lives (private
   GitHub repo, private Composer registry, or "it doesn't exist yet"). The
   answer was that its location is not known.

**What is not known, and now will not be resolved by further searching:**
whether `metrial-auth` exists as a real, reachable artifact anywhere. It may
be a real internal package this project's owner doesn't have direct visibility
into, or it may have been a planning-stage description of "assume we have
in-house identity tooling" that was never grounded in an actual accessible
package. Both are indistinguishable from here, and neither can be resolved by
more investigation from within this environment.

**Why this doesn't sit open indefinitely.** D-196 frames Phase 0 as bounded:
"validates assumptions before any product code," with the roadmap explicitly
warning that Phase 0 "seen as delay and skipped" is a risk to guard against —
the inverse failure, an unbounded Phase 0 that never exits, is equally a
failure of the same principle. Identity is upstream of every other context
(`03`); leaving it blocked indefinitely blocks Phase 1 (first revenue)
indefinitely too. A decision made under genuine uncertainty, stated as such, serves the codebase better than a stalled roadmap.

## Decision

Close TR-3 by building the Identity module (C1) in-house, inside the Core
API's own module structure, rather than continuing to block on an
unreachable dependency. `metrial-auth` is not adopted — not because it was
evaluated and found wanting, but because it could not be evaluated at all.

This does not reverse D-53 through D-64 (the RLS-based tenancy model) or the
work already built against them (`app/Tenancy/`, the RLS proof of concept,
`docs/governance/22-graph-traversal-benchmark-results.md`'s adjacent spike) —
none of that depended on which identity implementation sits above it, by
design.

## Reasoning

**Why:** of the options actually available — keep waiting, assume
compatibility, or build — building is the only one that doesn't require
either an indefinite stall or an unfounded assumption. The RLS-based
isolation model this build sits on top of is already proven (the Phase 0 RLS
spike, this session), so the highest-risk part of "build identity ourselves"
— getting tenant isolation right — is not new risk being taken on here.

**Why not the alternatives:**

- **Continue blocking.** Keeps Phase 0 "honest" a little longer at the cost
  of turning a bounded spike into an unbounded stall — the exact failure mode
  D-196 and the roadmap's own risk register warn against.
- **Assume `metrial-auth` is compatible and build against an assumed API.**
  This is precisely the assumption D-20 and D-195 were written to prevent.
  Building an entire Identity module against a guessed integration surface,
  only to discover incompatibility later, is the "rework in the most
  expensive possible place" TR-3 exists to name.
- **Substitute a similarly-named public package as a stand-in.** Confirmed
  not to exist on Packagist under any plausible vendor name. Evaluating an
  unrelated package and calling it "the metrial-auth evaluation" would be a
  fabricated finding wearing a real decision's name — worse than no
  evaluation at all.

**Future scalability:** building in-house means the Identity module is
designed exactly to `07`'s RLS/RBAC/ABAC requirements from the first line of
code, with nothing to adapt around an external package's own assumptions
about tenancy, session state, or role modeling.

**Maintenance cost:** materially higher than reuse would have been. This
project now owns the full identity surface — SSO, SAML/OIDC, SCIM, WebAuthn,
ABAC, adaptive MFA — that `metrial-auth` was supposed to remove from the
critical path. That cost is real and is not minimized here.

**Operational cost:** no third-party identity-platform licensing or vendor
relationship; the cost moves entirely into engineering time.

**Migration risk (of this decision itself): low.** The Domain layer is
framework-free (D-130) and no other bounded context holds a foreign key into
Identity's internals (D-461, cross-context references are plain identifiers).
If `metrial-auth`'s actual location is ever confirmed, replacing the
Infrastructure/Presentation layers of an in-house Identity module is a
contained change, not a platform-wide one.

**Business impact:** unblocks Phase 1 (first revenue). Accepts the "months
removed from the critical path" cost that `06` cited as the strongest reason
for choosing Laravel — that argument no longer holds in practice, even though
Laravel's other justifications (team fluency, workload fit) are unaffected.

## Alternatives considered

| Option | Strengths | Why not chosen |
| --- | --- | --- |
| Continue blocking Phase 1 until `metrial-auth` is located | Never risks building the "wrong" thing | Turns a bounded Phase 0 spike into an unbounded stall; blocks first revenue indefinitely on a dependency nobody can currently locate |
| Assume `metrial-auth` is compatible, build against a guessed API | Fastest apparent path | Exactly the unvalidated assumption D-20/TR-3 exist to prevent; if wrong, rework happens on committed schema and shipped code |
| Evaluate a similarly-named public package as a stand-in | Something concrete to evaluate | Confirmed not to exist on Packagist; would misrepresent an unrelated package's findings as this decision |
| **Build Identity in-house on Laravel's own primitives (chosen)** | Unblocks Phase 1; designed exactly to our RLS/ABAC model; the only option all the needed facts support | Loses the reuse argument that was `06`'s strongest justification for Laravel; largest net-new engineering surface in the platform |

## Consequences

**Positive:** Phase 1 is unblocked. The Identity module can be designed
precisely to `07`'s isolation and authorization model without adapting to an
external package. TR-3 is closed with an honest, evidence-based record
instead of sitting open indefinitely.

**Negative:** the primary reuse benefit `06` attributed to the Laravel choice
does not materialize. Identity — SSO, SAML/OIDC, SCIM, WebAuthn, ABAC,
adaptive MFA — is now this project's own largest, highest-risk build, on the
same timeline pressure Phase 1 already carries.

**Neutral:** `app/Tenancy/` (RLS binding, tenant context) is unaffected — it
was deliberately built identity-decision-agnostic for exactly this reason.

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| In-house identity misses a compliance requirement `metrial-auth` would have covered out of the box | Delayed enterprise readiness | Design SSO/SCIM/MFA against `07`/`09` as explicit, scoped Identity deliverables from the start, not retrofitted |
| `metrial-auth`'s real location surfaces later, after build effort is sunk | Wasted engineering time | Framework-free Domain layer and no cross-context FKs into Identity (D-461) keep a later swap contained, not a rewrite |
| Team underestimates the Identity module's build effort | Phase 1 timeline slips | Scope and estimate Identity as its own deliverable, not folded into "foundation" as a rounding error |

## Dependencies

- **Depends on:** `03` (C1 scope), `06` (technology decisions), `07`
  (multi-tenancy strategy), `09` (security strategy), the Phase 0 RLS proof
  of concept (`app/Tenancy/`, `apps/api/tests/Isolation/`).
- **Depended on by:** Phase 1's real Identity module implementation; closes
  the D-20/D-195 exit criterion in `docs/governance/20-roadmap.md`.

## Future Improvements

- If `metrial-auth`'s actual location is ever confirmed, evaluate it against
  the in-house implementation's real, by-then-measured cost — this ADR
  closes the indefinite block, not the question permanently.
- Track the Identity module's actual build effort against the "months"
  `06` attributed to reuse, as a data point for future build-vs-buy calls.
