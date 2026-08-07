# Shared

UI primitives, hooks and utilities used by more than one feature, plus the API
client generated from `packages/contracts`.

**Does not own:** feature-specific logic — if something is used by exactly one
feature, it belongs in that feature, not here. `shared/` is never imported
_from_ `features/` back into a feature-specific concern; the dependency runs
one way.
