# Identity

The shared-kernel Identity and Tenancy context (C1,
`docs/product/03-core-modules-and-scope.md`) — `Tenant`, `User`,
`Membership` (`docs/architecture/data/42-entity-model-and-ownership.md`),
authentication, and tenant-level RBAC
(`docs/architecture/07-multi-tenancy-strategy.md`).

## Not built on `metrial-auth`

No code here requires, imports, or depends on the `metrial/auth` package or
any `Metrial\*` namespace. It doesn't exist as an installable dependency —
see `docs/architecture/adr/0001-metrial-auth-evaluation-outcome.md` and
`docs/architecture/adr/0004-metrial-auth-reference-architecture-only.md`.
Where this module's shape (Services/Repositories/Actions/DTOs split, one
directory per concern) resembles what a Claude Code skill documents about
that package, it's because ADR-0004 treats that documentation as a design
reference — nothing more.

## Scope

**In:** email+password registration and login (Sanctum tokens), tenant
provisioning (atomic: tenant + owner + membership,
`docs/architecture/07-multi-tenancy-strategy.md` Tenant Lifecycle),
tenant-level RBAC (Owner/Admin/Delivery Manager/Architect/Contributor/
Viewer).

**Out, deliberately** — later phases per `docs/product/03-core-modules-and-scope.md`,
not because they're hard to imagine, but because Phase 1 doesn't need them
yet: SSO (SAML/OIDC/LDAP), SCIM provisioning, WebAuthn/passkeys, adaptive
MFA, the ABAC policy engine (`PolicyRule`), external stakeholder grants
(`StakeholderGrant`), project-level role overrides (`Project` doesn't exist
yet either).

## Layout

Standard module layering (`docs/delivery/11-repository-and-folder-strategy.md`):

```
Domain/           Tenant, User, Membership entities; Role/TenantStatus/
                  MembershipStatus enums; repository interfaces. Framework-free
                  (D-130) -- checked by tests/Architecture/DomainLayerHasNoFrameworkDependencyTest.php.
Application/      RegisterTenant, AuthenticateUser, AssignMembershipRole use cases.
Infrastructure/   Eloquent models and repository implementations, the
                  Sanctum-backed TokenIssuer, the service provider binding
                  Domain interfaces to these implementations.
Presentation/     Controllers, FormRequests, and BindTenantContext -- the
                  middleware that resolves and binds the tenant for every
                  authenticated request (see its own docblock for the
                  bootstrapping problem it solves).
```

## The `memberships` table

The first real tenant-scoped, RLS-enforced table in the platform
(`database/migrations/*_identity_create_memberships_table.php`), replacing
the Phase 0 RLS proof of concept. Its RLS policy has one addition beyond
the spike's: a read-only carve-out letting a user see their own membership
rows across *all* tenants (not just the currently-bound one), because
"which tenants does this user belong to" must be answerable before any
tenant is bound — see the migration's own comment and
`tests/Isolation/MembershipIsolationTest.php`.
