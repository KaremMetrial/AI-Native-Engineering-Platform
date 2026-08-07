# ADR-0004: Use `metrial-auth`'s Documented Architecture as a Design Reference Only

- **Status:** Accepted
- **Date:** 2026-08-07
- **Deciders:** Engineering leadership
- **Supersedes:** none — amends ADR-0001, does not reverse it
- **Related:** ADR-0001, D-20, D-461, D-130, `docs/architecture/07-multi-tenancy-strategy.md`

## Context

ADR-0001 closed TR-3 on the finding that `metrial-auth`'s actual location
could not be determined after checking public package registries, the
foundation documents, and the project owner directly — and committed to
building the Identity module in-house.

Immediately afterward, a Claude Code skill named `metrial-auth` was
discovered in this environment (`/root/.claude/skills/metrial-auth`),
containing detailed reference documentation: module list, config shape,
generated file tree, event names, Artisan install wizard flow, and code
snippets for a Laravel package published as `metrial/auth`.

This looked, at first read, like it could reverse ADR-0001 — a real access
path finally surfacing. It was checked, not assumed:

```
composer show metrial/auth        → "Package not found"
curl repo.packagist.org/p2/metrial/auth.json → 404 not found, no packages here
```

**The package remains unpublished and uninstallable.** The skill is prompt
context — reference material for generating code in the *style* of what
`metrial-auth:install` would produce — not a package registry entry, a
repository, or a working dependency. Treating the skill's presence as
"access restored" and writing `composer require metrial/auth` into any
committed file would produce a build that fails for the next person (and
in CI) the moment dependencies are installed for real — exactly the kind of
fake implementation the charter forbids, and arguably a more convincing fake
than before, precisely because the reference material is this detailed and
plausible.

## Decision

ADR-0001's decision stands: Identity is built in-house, not on the
`metrial/auth` package, because that package still cannot actually be
installed.

What changes: the in-house Identity module **adopts `metrial-auth`'s
documented module boundaries, config shape, and naming conventions where
they fit our own architecture**, treating the skill as a well-specified
design reference — the way a whitepaper or a competitor's public API docs
would inform a design without being a dependency. Nothing in code may
`require` or `use` a `Metrial\*` namespace or the `metrial/auth` package;
every class the in-house module needs is written in this codebase, under
`app/Modules/Identity/` per `11`'s module layout (Domain/Application/
Infrastructure/Presentation), following D-130 (framework-free Domain layer)
and D-461 (no cross-context FKs) exactly as ADR-0001 already required.

## Reasoning

**Why this is a real change worth its own record, not a footnote on
ADR-0001:** ADR-0001 is accepted and immutable by the ADR process's own
rule 2 — its reasoning reflected what was true when it was written. This
new fact (a detailed reference exists, the package itself still doesn't)
arrived after that record closed, and deserves its own account rather than
a silent edit that would hide what was known when.

**Why adopt the documented conventions at all, if we're not using the
package:** `metrial-auth`'s documented architecture (Services/
Repositories/Actions/DTOs/Events/Listeners/Policies split, one directory per
concern) is a reasonable, well-precedented shape for an identity module
regardless of its origin, and building to match a documented API surface
gives future engineers (or a real `metrial-auth`, if it is ever actually
located) a smaller diff to reconcile against than an unrelated home-grown
shape would.

**Why not adopt it wholesale as scope:** the skill documents an enormous
surface — SSO (SAML/OIDC/LDAP), SCIM, WebAuthn, an AI risk engine, Zero
Trust, PAM, identity governance, a full OAuth2 authorization server.
`docs/product/03-core-modules-and-scope.md` scopes Phase 1's C1 delivery as
"identity and tenancy with RLS enforcement" — authentication, membership,
RBAC. Building the full documented surface now would be scope invention
this charter explicitly forbids ("never add features... beyond what the
task requires"); it is picked up incrementally, module by module, only when
a real Phase requires it (SSO in Phase 2+ per `03`'s phasing, not Phase 1).

**Why not `composer require metrial/auth` speculatively, expecting it to
resolve later:** a dependency declaration that doesn't resolve breaks every
fresh install and every CI run today, for a hope about tomorrow. `06`'s own
technology-selection principle (`24`) requires reversibility judged on
today's facts, not a wished-for future one.

## Alternatives considered

| Option | Strengths | Why not chosen |
| --- | --- | --- |
| Treat the skill's presence as resolving ADR-0001; try `composer require metrial/auth` | Fastest apparent path if it worked | Confirmed not to work — the package doesn't exist on Packagist under this name either |
| Ignore the skill entirely, build Identity from a blank design | Avoids any appearance of depending on an unavailable package | Wastes a genuinely useful, detailed reference architecture for no reason — the skill's content doesn't stop being good design guidance just because the package isn't installable |
| **Use the skill as design reference only, scoped to Phase 1's actual needs (chosen)** | Gets the value of a well-specified architecture without depending on anything unreachable; stays honest about what is and isn't real | Requires discipline to not silently over-scope toward the skill's full enterprise surface |

## Consequences

**Positive:** the in-house Identity module gets a credible, detailed
architectural reference instead of a from-scratch design; future
integration with a real identity package (if one is ever actually located)
has a smaller gap to close.

**Negative:** none beyond what ADR-0001 already accepted — this doesn't add
scope or cost, it constrains how the existing in-house build is shaped.

**Neutral:** no code, dependency, or config changes yet — this ADR governs
how the Identity module about to be built is designed, not anything already
shipped.

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Scope creep toward the skill's full documented surface (SAML, LDAP, PAM, risk engine, zero trust) inside Phase 1 | Phase 1 timeline slips chasing enterprise features no Phase 1 customer needs | Explicit Phase 1 scope boundary in this ADR and in the Identity module's own README; SSO/SCIM/etc. deferred to their named phase in `03` |
| A future engineer assumes `metrial/auth` is a real dependency because the code "looks like" it follows the package's conventions | Confusion, wasted investigation time | This ADR and the Identity module's README state plainly, in the first paragraph, that no such package is installed |

## Dependencies

- **Depends on:** ADR-0001 (does not reverse it).
- **Depended on by:** the Identity module's implementation, about to begin.

## Future Improvements

- If `metrial/auth` (or `metrial-auth` under any other vendor name) is ever
  confirmed to actually exist and be installable, re-open ADR-0001's
  question with real package contents to evaluate against — not just its
  documented API surface.
- As Phase 2+ brings SSO/SCIM into scope (`03`), revisit which parts of the
  skill's documented surface are still the right reference at that point.
