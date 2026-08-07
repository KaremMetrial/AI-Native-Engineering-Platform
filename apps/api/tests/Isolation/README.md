# Isolation Suite

Deliberately empty until the Tenancy module exists (Phase 0 spike, in
progress -- see the project task list). T-1 (cross-tenant data leak) is the
one requirement whose failure is unrecoverable, so this suite gets its own
top-level directory (D-107) rather than living inside `Feature/`.

**A fake passing test here would be worse than an honest absence.** Do not
add a placeholder test that asserts something trivial just to make this
directory non-empty -- that produces a green checkmark for a security
property nothing has verified yet, which is a more dangerous state than a
visibly empty suite.

What lands here, per `docs/delivery/14-testing-strategy.md`:
- Cross-tenant read/write denial by any route, including direct ID access
- RLS is actually active (a deliberately unscoped query returns zero rows)
- The application role has neither `BYPASSRLS` nor table ownership (D-55) --
  proven at the database level, not merely configured
- Connection pool isolation under concurrent multi-tenant requests
- Async job and event context propagation, failing closed when absent
