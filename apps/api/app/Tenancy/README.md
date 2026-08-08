# Tenancy

Tenant resolution, RLS binding, and context propagation
(`docs/delivery/11-repository-and-folder-strategy.md`; `docs/architecture/07-multi-tenancy-strategy.md`).

## What lives here

`Infrastructure/TenantContext.php` binds and clears the Postgres session
variables (`app.tenant_id`, `app.user_id`) that every RLS policy reads
(layer 3 of the six-layer model in `07`). Bound as a singleton
(`Infrastructure/TenancyServiceProvider.php`) -- every consumer within one
request must observe the same bound state, since the underlying Postgres
session variable is shared but each fresh instance's in-memory copy would
not be.

Tenant *resolution* (layers 1-2: reading the authenticated token, rejecting
an unresolvable tenant) lives in `app/Identity/Presentation/BindTenantContext.php`
now that the Identity module exists -- see that module's README for why
that split (mechanism here, identity-dependent resolution there) held up
once real identity code landed.

## The RLS proof of concept -- resolved

`database/migrations/2026_08_07_000001_create_rls_poc_scoped_items_table.php`'s
throwaway table (`rls_poc_scoped_items`) is dropped by
`database/migrations/2026_08_07_200002_identity_create_memberships_table.php`,
exactly as planned: `memberships` is now the real, first tenant-scoped RLS
table, and `tests/Isolation/MembershipIsolationTest.php` is the real proof
suite that replaced `tests/Isolation/RlsIsolationTest.php`.
