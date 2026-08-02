# Multi-Tenancy Strategy

## Purpose

Define how tenants are isolated, how tenant context is resolved and enforced,
and how the model evolves as customers grow. Tenancy is the most expensive
architectural decision to reverse and the one whose failure is least
survivable — a single cross-tenant leak at an agency exposes one client's
confidential data to another.

## Scope

**In scope:** isolation model, tenant context propagation, enforcement
mechanisms, authorization model, external stakeholder access, noisy-neighbour
control, tenant lifecycle, and the promotion path to stronger isolation.

**Out of scope:** general application security (`09-security-strategy.md`) and
identity provider mechanics (an implementation deliverable).

---

## Tenancy Model

**A tenant is an organization** — one agency or one company. It owns projects,
members, integrations, billing, and all artifacts.

Below the tenant: **projects**, the primary work-scoping unit. Most
authorization decisions are project-scoped rather than tenant-wide, because
agency staff routinely work on some of a tenant's projects and not others.

Deliberately **not** supported: nested tenants, tenant hierarchies, or a single
user account genuinely shared across tenants. A user may belong to several
tenants through separate memberships, but there is no shared identity across
tenant boundaries and no inheritance between tenants. Hierarchies are requested
by large customers, add substantial permission-model complexity, and can be
introduced later behind the same authorization interface if a real customer
funds them. Building them speculatively would complicate every query in the
system for a customer we do not yet have.

---

## Isolation Model: Shared Database, Shared Schema, RLS-Enforced

**Decision:** all tenants share one database and one schema. Every
tenant-scoped table carries a non-nullable `tenant_id`. Isolation is enforced
by **PostgreSQL Row-Level Security**, not by application query discipline.

### The layered enforcement

Isolation is defence in depth. Each layer independently prevents a leak, so a
single mistake is contained:

| Layer | Mechanism | Prevents |
| --- | --- | --- |
| 1. Edge | Tenant resolved from the authenticated token; never from a client-supplied header or parameter | Trivial tenant spoofing |
| 2. Request context | Tenant bound immutably to request context; unresolvable tenant = request rejected | Ambiguous or absent tenant context |
| 3. Database session | Tenant set as a session variable on the connection before any query | Application forgetting to scope |
| 4. **RLS policies** | Postgres policies filter every row by the session tenant | **Everything above failing** |
| 5. Application scoping | Repositories scope by tenant explicitly | Wrong results and full-table scans; also keeps intent visible in code |
| 6. Verification | Automated cross-tenant isolation suite, blocking CI gate (T-1) | Regressions reaching production |

**Layer 4 is the one that matters most.** Every isolation model that depends
solely on application discipline eventually fails — one forgotten `where` in
one query, one raw SQL statement, one clever join. With RLS, a query missing
its tenant scope returns **zero rows**, not another tenant's data. The failure
mode becomes a visible bug rather than a silent breach.

**Critical implementation constraint:** the application's database role must
**not** hold `BYPASSRLS`, and must not be the table owner (owners bypass RLS by
default). Migrations run as a separate privileged role. Getting this wrong
silently disables the entire isolation model while every test still passes — so
it is verified explicitly by the isolation suite rather than assumed.

### Why not the alternatives

| Model | Advantages | Why not chosen |
| --- | --- | --- |
| **Schema per tenant** | Strong isolation; simple per-tenant backup/export | Migrations must run across N schemas — at 50k tenants (S-1) this is hours of deploy time and a partial-failure nightmare. Connection pooling degrades. Cross-tenant analytics become painful. **Operationally unviable at our target scale.** |
| **Database per tenant** | Strongest isolation; per-tenant tuning and residency | Cost per tenant is dominated by fixed database overhead, destroying margin on small tenants (G5). Thousands of databases to provision, migrate, monitor and back up. Viable only for a handful of enterprise accounts — which is exactly how we use it (see promotion path). |
| **Shared schema, application-only filtering** | Simplest | One forgotten filter is a breach. Relies on perfect discipline forever, across every engineer and every future raw query. **Rejected on principle**: unacceptable for a failure that cannot be recovered from. |
| **Shared schema + RLS** *(chosen)* | Single migration path; efficient pooling; margin-friendly on small tenants; database-enforced isolation | Requires Postgres-specific features (accepted — D-43); demands careful role configuration; noisy-neighbour must be managed explicitly |

**Trade-offs accepted:**

- **Postgres coupling.** RLS is not portable. Accepted: it is a deciding
  advantage, and moving off Postgres is not a plausible near-term event.
- **`tenant_id` on every table and index.** Slight storage and index overhead,
  and composite indexes must lead with `tenant_id`. This is also a performance
  *benefit* — it partitions data naturally.
- **Blast radius.** A database incident affects all tenants. Mitigated by
  managed HA, replicas, PITR (A-6/A-7), and the promotion path for customers
  who need more.
- **Noisy neighbours.** Explicitly managed below rather than wished away.

### Promotion path to stronger isolation

Enterprise customers will demand dedicated infrastructure, and some will pay
for it. Because tenant scoping is uniform and every access is already
tenant-qualified, a tenant can be promoted to a **dedicated schema** or a
**dedicated database** by changing connection routing — **without application
changes** (T-6).

This is why the promotion path costs almost nothing to preserve: the same
`tenant_id` discipline that enables RLS is what makes extraction mechanical.
The requirement is that no code ever assumes all tenants share one connection.

Deferred deliberately: cross-region residency (C-4) is Phase 4 and requires
regional deployment, not just a routing change.

---

## Tenant Context Propagation

Tenant context must survive every boundary. A context lost mid-flow is a
potential leak, and the async paths are where this is most often mishandled.

| Boundary | Propagation |
| --- | --- |
| HTTP request | Resolved from token claims at the edge; bound to request context |
| Database | Session variable set on connection checkout; reset on release |
| Queued job | Serialized into the job payload; re-established before the handler runs |
| Domain event | Carried on the event envelope |
| AI orchestration call | Passed explicitly; the service refuses requests without it |
| Cache key | Tenant ID is part of every key, structurally |
| Object storage path | Tenant ID is part of every prefix |
| Logs, metrics, traces | Attached to every record (O-3) |

**Two failure modes are called out because they are the ones that actually
happen:**

1. **Connection pool leakage.** If a pooled connection retains the previous
   tenant's session variable, the next request sees the wrong data. The
   variable must be set on checkout and cleared on release, and this must be
   tested explicitly under concurrency — not assumed.
2. **Async context loss.** A job that runs without tenant context either fails
   closed or, worse, runs unscoped. **Fail closed, always**: a missing tenant
   context is a fatal error, never a fallback to "all tenants."

**Cache key discipline** is not merely convention — a key builder that requires
tenant ID makes the correct behaviour the only available behaviour.

---

## Authorization Model

Two mechanisms, deliberately combined because neither is sufficient alone.

### Role-based (RBAC) — the common case

Roles within a tenant: Owner, Admin, Delivery Manager, Architect, Contributor,
Viewer. Roles are assignable at tenant level and overridable per project, which
covers the overwhelming majority of decisions clearly and cheaply.

### Attribute-based (ABAC) — where roles are insufficient

Roles alone cannot express the rules three personas require:

- **P1 Founder** sees commercial and margin data that a Contributor with an
  otherwise identical project role must not.
- **P8 External stakeholder** sees only explicitly shared artifacts of one
  project — a per-resource grant, not a role.
- **Approval authority** depends on artifact type and value (a contract above a
  threshold needs Owner approval), which is an attribute of the resource, not
  of the actor.

So the policy layer evaluates actor attributes, resource attributes, and
explicit grants. This is more complex than pure RBAC and is adopted only
because concrete requirements demand it, not for generality.

### Non-negotiable rules

- **Default deny** (SEC-4). Absence of a matching policy denies. Every endpoint
  carries an explicit policy; an architecture test fails the build on any
  endpoint without one.
- **Authorization is never the only isolation control.** It sits above RLS, not
  instead of it.
- **Permission checks are centralized** in a policy layer, never scattered as
  inline conditionals — otherwise they cannot be audited or tested as a set.
- **`metrial-auth` evaluation** covers this model; its ABAC engine may satisfy
  these requirements directly (D-20).

---

## External Stakeholder Access

The highest-risk access path in the system: users outside the tenant who must
see part of it.

- Separate identity type — an external stakeholder is **not** a tenant member
  and cannot be granted a member role.
- Access via **explicit, per-resource, revocable, expiring grants**. No implicit
  inheritance from project or tenant membership.
- Read and comment/approve only; never write to artifacts directly.
- Every grant, access and revocation is audited.
- Grants expire by default. An unexpiring share is an eventual leak.

**Design rationale:** the natural implementation — "give the client a limited
role" — is wrong, because a role is a bundle of capabilities within a tenant
and every future capability added to that role silently extends what the
external party can see. A grant model only ever exposes what was explicitly
shared, so adding features cannot accidentally widen external access.

---

## Noisy Neighbour Control

Shared infrastructure means one tenant can degrade another (S-9).

| Control | Purpose |
| --- | --- |
| Per-tenant API rate limits | Prevent request-volume monopolization |
| Per-tenant queue fairness | Prevent one tenant's bulk generation starving others' jobs |
| Per-tenant AI spend caps ($-4) | Prevent both cost overrun and inference-capacity monopolization |
| Per-tenant storage quotas | Prevent unbounded storage growth |
| Query timeouts | Prevent one expensive query saturating the database |
| Per-tenant usage telemetry (O-3) | Detect degradation *before* customers report it |

**Queue fairness deserves emphasis.** A single FIFO queue means a tenant
enqueuing 500 documents delays every other tenant behind them — a small
customer's single urgent job sits behind an hour of someone else's bulk work.
Per-tenant queue partitioning with round-robin consumption is a Phase 2
requirement, not an optimization.

---

## Tenant Lifecycle

| Stage | Requirements |
| --- | --- |
| **Provisioning** | Atomic: tenant, owner, defaults, entitlements. Partial provisioning leaves an unusable account. |
| **Suspension** | Access blocked, data retained. Reversible. |
| **Export** (T-4) | Complete, machine-readable, self-service. A GDPR obligation *and* an anti-lock-in trust signal — customers commit more readily to a platform they can leave. |
| **Deletion** (T-5) | Verified complete within 30 days, across database, object storage, caches, search indexes, vector storage, and backups per retention policy. |

**Deletion completeness is routinely underestimated.** Tenant data spreads to
every derived store — vectors, search indexes, caches, exports, logs. Each
requires an explicit deletion path, and the deletion audit must verify each one
rather than trusting a single cascade.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-53 | Shared database, shared schema, `tenant_id` on every tenant-scoped table | Only model that satisfies S-1 scale and G5 margin simultaneously |
| D-54 | Isolation enforced by Postgres RLS, not application discipline | Missing scope returns zero rows instead of another tenant's data |
| D-55 | Application DB role must not have BYPASSRLS or own the tables | Otherwise RLS is silently inert while all tests pass |
| D-56 | Tenant resolved only from authenticated token claims | Client-supplied tenant identifiers are trivially forgeable |
| D-57 | Missing tenant context is a fatal error; never a fallback | Fail closed — the only safe default |
| D-58 | Tenant ID structurally required in cache keys and storage paths | Makes correct behaviour the only available behaviour |
| D-59 | RBAC for common cases, ABAC where personas require it | Roles cannot express P1, P8 or value-based approval |
| D-60 | External stakeholders use per-resource grants, never roles | Role-based sharing silently widens as roles gain capabilities |
| D-61 | Shares expire by default | An unexpiring share is an eventual leak |
| D-62 | Per-tenant queue fairness in Phase 2 | Single FIFO lets one tenant starve all others |
| D-63 | Promotion to dedicated schema/database without application changes | Preserves the enterprise path at near-zero present cost |
| D-64 | No tenant hierarchies until a paying customer requires them | Complicates every query for a customer we do not have |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| RLS misconfigured (privileged role, table ownership) | Isolation silently absent | Explicitly verified by the isolation suite, including a test that RLS actually blocks a bypass attempt |
| Connection pool leaks tenant session state | Cross-tenant data exposure under concurrency | Set on checkout, clear on release, test under concurrent load |
| Async job runs without tenant context | Unscoped processing or wrong-tenant writes | Fail closed; context is a required job field |
| Derived stores (vector, search, cache) not tenant-filtered | Leak through the retrieval path (T-7) | Tenant filter applied before retrieval; covered by the isolation suite |
| Noisy neighbour degrades platform for others | Churn among small customers | Per-tenant limits, quotas and fairness; usage telemetry |
| Tenant deletion incomplete across derived stores | Compliance violation | Explicit deletion path per store, verified by audit |
| RLS overhead on hot queries | Latency regression (P-1) | Benchmark with policies enabled; tenant-leading composite indexes |

## Dependencies

- **Depends on:** personas (`02`), NFRs (`04`), architecture (`05`), Postgres
  choice (`06`).
- **Depended on by:** security strategy, scalability, AI strategy (retrieval
  scoping), testing strategy (isolation suite), deployment.

## Future Improvements

- Publish the isolation test suite specification before the first tenant-scoped
  table is created — the tests should exist before the schema does.
- Benchmark RLS overhead against P-1 targets on realistic data volumes.
- Design regional deployment for C-4 data residency ahead of Phase 4.
- Revisit tenant hierarchies only when a signed customer requires them.
