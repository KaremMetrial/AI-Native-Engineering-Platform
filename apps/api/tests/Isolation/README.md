# Isolation Suite

T-1 (cross-tenant data leak) is the one requirement whose failure is
unrecoverable, so this suite gets its own top-level directory (D-107) rather
than living inside `Feature/`.

## Phase 0: RLS proof of concept

`RlsIsolationTest.php` proves the Postgres RLS mechanism itself, ahead of any
real tenant-scoped schema (`docs/governance/20-roadmap.md`'s Phase 0 exit
criterion: "Isolation model proven, including a test that RLS actually blocks
a bypass attempt"). It runs against a throwaway table
(`database/migrations/2026_08_07_000001_create_rls_poc_scoped_items_table.php`,
see `app/Tenancy/README.md` for why it is not the real Identity schema) and
covers everything `docs/delivery/14-testing-strategy.md` asks of this suite
that Phase 0 can actually exercise:

- Cross-tenant read denial, including direct ID access
  (`test_a_tenant_only_sees_its_own_rows`,
  `test_direct_id_access_to_another_tenants_row_returns_nothing`)
- Cross-tenant write denial (`test_a_tenant_cannot_write_a_row_for_another_tenant`)
- RLS is actually active: a missing tenant context returns zero rows, not
  another tenant's data (`test_missing_tenant_context_returns_zero_rows_not_another_tenants_data`)
- **A bypass attempt is actually blocked**, not merely assumed impossible:
  `SET row_security = off` errors instead of leaking rows
  (`test_row_security_off_bypass_attempt_is_blocked`), and `ALTER TABLE ...
  DISABLE ROW LEVEL SECURITY` is refused as a permissions error
  (`test_runtime_role_cannot_disable_row_level_security`)
- The application role has neither `BYPASSRLS` nor table ownership (D-55),
  proven at the database level via `pg_roles`/`pg_tables`, not merely
  configured (`test_runtime_role_has_neither_bypassrls_nor_table_ownership`)
- Session state does not leak across a simulated connection
  checkout/release cycle (`test_context_does_not_leak_across_a_simulated_connection_checkout_cycle`)

**Deliberately not yet covered, and why that is honest rather than a gap
being hidden:**

- *Real connection-pool concurrency.* This environment has no PgBouncer or
  equivalent in front of Postgres, so "checkout/release" above is simulated
  sequentially within one connection rather than proven under genuine
  concurrent multi-tenant load. Revisit once a pooler is in the deployment
  path.
- *Async job and event context propagation.* No queue infrastructure runs
  jobs yet (`QUEUE_CONNECTION=sync` in tests) — there is nothing to test
  until Phase 1 introduces real queued work. `TenantContext` (`app/Tenancy/`)
  is written so a job dispatcher can bind it from a serialized payload when
  that lands.
- *Route-level cross-tenant denial.* There are no tenant-scoped HTTP routes
  yet (D-20 blocks the Identity module, see `app/Tenancy/README.md`). This
  suite proves the database layer; the same proof repeats at the route layer
  once real endpoints exist.

**A fake passing test would be worse than an honest absence** — nothing
above claims more than what actually runs.
