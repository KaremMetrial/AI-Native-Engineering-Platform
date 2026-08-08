# Isolation Suite

T-1 (cross-tenant data leak) is the one requirement whose failure is
unrecoverable, so this suite gets its own top-level directory (D-107) rather
than living inside `Feature/`.

## `MembershipIsolationTest.php` — the real proof, against real data

Replaces the Phase 0 RLS proof of concept (`RlsIsolationTest.php`, deleted)
now that `memberships` (`app/Identity/`) is a real, tenant-scoped,
RLS-enforced table — the exact transition `app/Tenancy/README.md` and the
original spike's docs said would happen. Same structure of proofs, same
reasoning, run against production schema instead of a throwaway one:

- Cross-tenant read denial, including direct ID access
  (`test_a_tenant_only_sees_its_own_memberships`,
  `test_direct_id_access_to_another_tenants_membership_returns_nothing`)
- Cross-tenant write denial (`test_a_tenant_cannot_write_a_membership_into_another_tenant`)
- RLS is actually active: a missing tenant context returns zero rows, not
  another tenant's data (`test_missing_tenant_context_returns_zero_rows_not_another_tenants_data`)
- **A bypass attempt is actually blocked**: `SET row_security = off` errors
  instead of leaking rows (`test_row_security_off_bypass_attempt_is_blocked`),
  and `ALTER TABLE ... DISABLE ROW LEVEL SECURITY` is refused as a
  permissions error (`test_runtime_role_cannot_disable_row_level_security`)
- The application role has neither `BYPASSRLS` nor table ownership (D-55),
  proven at the database level via `pg_roles`/`pg_tables`
  (`test_runtime_role_has_neither_bypassrls_nor_table_ownership`)
- **New in this suite, not present in the Phase 0 spike**: the self-membership
  read carve-out (`app/Identity/README.md`) grants exactly the visibility it
  should and no more — a user sees their own memberships across tenants
  (`test_a_user_can_see_their_own_memberships_across_tenants_without_a_tenant_bound`)
  but never another user's, even within the same bound tenant
  (`test_the_self_membership_carveout_does_not_expose_other_users_memberships`)

**Route-level cross-tenant denial** is now covered too, in
`tests/Feature/Identity/` — `AssignRoleTest.php`'s "a contributor cannot
promote another member" case, exercised through `BindTenantContext` and the
real HTTP layer, not just the database directly.

**Still deliberately not covered, and why that is honest rather than a gap
being hidden:**

- *Real connection-pool concurrency.* This environment has no PgBouncer or
  equivalent in front of Postgres, so checkout/release is proven
  sequentially, not under genuine concurrent multi-tenant load. Revisit once
  a pooler is in the deployment path.
- *Async job and event context propagation.* No queue infrastructure runs
  jobs yet (`QUEUE_CONNECTION=sync` in tests) — nothing to test until real
  queued work exists. `TenantContext` (`app/Tenancy/`) is written so a job
  dispatcher can bind it from a serialized payload when that lands.

**A fake passing test would be worse than an honest absence** — nothing
above claims more than what actually runs.
