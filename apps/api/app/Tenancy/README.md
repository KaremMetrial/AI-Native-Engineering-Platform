# Tenancy

Tenant resolution, RLS binding, and context propagation
(`docs/delivery/11-repository-and-folder-strategy.md`; `docs/architecture/07-multi-tenancy-strategy.md`).

## What lives here

`Infrastructure/TenantContext.php` binds and clears the Postgres session
variable (`app.tenant_id`) that every RLS policy reads (layer 3 of the six-
layer model in `07`). It is real, production-durable code: the mechanism is
identical regardless of where the tenant identifier comes from, so it does
not depend on the identity decision below.

## What deliberately does not live here yet

Resolving *which* tenant a request belongs to (layers 1–2: reading the
authenticated token, rejecting an unresolvable tenant) requires an identity
and auth mechanism. Per D-20, evaluating `metrial-auth` as that mechanism
requires ADR-0001 before any identity code is committed — tracked as its own
Phase 0 spike, currently blocked pending input on environment access to the
package. Building tenant *resolution* ahead of that decision would mean
redoing it once the identity ADR lands, and risks pre-committing to a shape
the real identity package may not need.

The shared-kernel `Tenant`/`User`/`Membership` entities
(`docs/architecture/data/42-entity-model-and-ownership.md`) land with the
real Identity module in Phase 1, once ADR-0001 is resolved — not here.

## The RLS proof of concept

`database/migrations/2026_08_07_000001_create_rls_poc_scoped_items_table.php`
creates a throwaway table, `rls_poc_scoped_items`, whose only purpose is to
prove the Postgres RLS mechanism itself — see `tests/Isolation/`. It is not
part of any bounded context's schema and is deleted once Phase 1 replaces it
with the real Identity module and its first genuinely tenant-scoped tables.
