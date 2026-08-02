# Audit and Temporal Data Architecture

## Purpose

Define the audit data model and how historical state is preserved and queried.
`09` states what must be audited; this document specifies the data structures
that make the audit trail complete, tamper-evident and usable — and separates
audit from the two things it is routinely confused with.

## Scope

**In scope:** the audit record model, tamper evidence, the audit/event/log
distinction, temporal state history, query patterns and tenant-facing audit
access.

**Out of scope:** security policy (`09`), event delivery (`35`), retention
schedule (`46`).

---

## Three Different Things

**Decision.** Audit entries, domain events and application logs are separate
mechanisms with separate stores, retention and guarantees. They are never
conflated or derived from one another.

| | **Audit entry** | **Integration event** | **Application log** |
| --- | --- | --- | --- |
| **Purpose** | Compliance and forensic record | Drive behaviour in other contexts | Diagnose the system |
| **Audience** | Auditors, tenant admins, security | Other contexts, projections | Engineers |
| **Guarantee** | Append-only, tamper-evident | At-least-once delivery | Best effort |
| **Retention** | 7 years (`46`) | 24 months | 30 days |
| **Content** | Who did what to which resource | Business fact and minimal payload | Diagnostics, no content |
| **Tenant-readable** | **Yes** | No | No |
| **Loss tolerance** | **Zero** | Zero (outbox) | Acceptable |

**Reasoning.** The temptation is to derive audit from events — they overlap
heavily, and one mechanism is simpler than three. It fails on four counts:

1. **Not every audited action produces a domain event.** A failed authorization
   attempt, a read of a sensitive record, a JIT support access — all must be
   audited, none is a business fact worth publishing.
2. **Not every event is audit-worthy.** Most are internal plumbing; an audit log
   containing every projection trigger is unusable by an auditor.
3. **Different retention.** Events expire at 24 months; audit must survive 7
   years. Retaining all events for 7 years to satisfy audit is expensive and
   still wrong, because the content differs.
4. **Different integrity guarantees.** Audit requires tamper evidence. Events
   require delivery. Building one mechanism to satisfy both means the strictest
   requirement governs everything, at the cost of everything.

**Alternatives.** *Audit derived from events* — fails as above. *Audit derived
from database triggers or CDC* — captures row changes rather than intent, so
"who approved this and why" becomes "this column changed", losing exactly what an
audit is for. *Logs as audit* — no integrity guarantee, wrong retention, and logs
are engineer-facing rather than tenant-readable.

**Trade-offs.** Three mechanisms to maintain, and a single action may write to
all three. Accepted: they answer different questions for different audiences with
different guarantees.

**Benefits.** Each is fit for its purpose. Audit remains small enough to be
usable and strict enough to be trusted.

**Long-term impact.** Audit trails are consulted years after the fact, usually
under pressure — a security incident, a customer dispute, a compliance review.
One that is incomplete or untrustworthy at that moment has failed at the only
time it mattered.

---

## Audit Record Model

| Field | Purpose |
| --- | --- |
| `id` | Identity |
| `tenant_id` | Scope — RLS-enforced; tenants read their own |
| `occurred_at` | When the action happened |
| `recorded_at` | When it was persisted |
| `actor_type` | user · external_stakeholder · system · platform_operator |
| `actor_id` | Who — null only for unauthenticated attempts |
| `actor_context` | IP, user agent, session, auth method, step-up status |
| `action` | Verb from a **closed vocabulary** |
| `resource_type`, `resource_id` | What was acted upon |
| `outcome` | success · denied · error |
| `reason` | Why it was denied, where applicable |
| `change_summary` | **Field names changed — never values** |
| `correlation_id` | Ties to the trace and to related entries |
| `sequence` | Monotonic per tenant, for gap detection |
| `previous_hash`, `entry_hash` | Tamper evidence (below) |

**`change_summary` records field names, not values** (D-84). Recording that
`pricing.margin` changed is auditable; recording that it changed from 22% to 18%
puts commercial data in a second store with different access controls and
seven-year retention. The prior value is recoverable from the versioned artifact
or the temporal history where those exist — which is the correct place for it.

**The action vocabulary is closed**, like link types (D-338): an open vocabulary
makes audit queries unknowable, because a search for "who deleted things" must
guess every verb anyone ever used.

---

## What Is Audited

| Category | Examples |
| --- | --- |
| **Authentication** | Login success and failure, MFA, step-up, session revocation, token reuse detection |
| **Authorization** | **Denials** — a denial spike is an attack signal; a grant is routine |
| **Membership and roles** | Grants, revocations, role changes, policy changes |
| **External access** | Stakeholder grant issued, accessed, expired, revoked |
| **Artifact lifecycle** | Approval, rejection, deletion, restoration, archival |
| **Commercial** | Proposal sent, contract generated, signature requested and completed |
| **Data movement** | Export requested and completed, DSAR fulfilled |
| **Configuration** | Integration credentials, SSO configuration, retention settings |
| **Platform operations** | JIT support access grant and use (D-76), feature flag changes, tenant lifecycle |
| **AI** | Generation with lineage reference, evaluation gate override |

**Authorization denials are audited; grants are not.** Auditing every successful
permission check would produce enormous volume of no forensic value. Denials are
low-volume and high-signal — a spike is an attack indicator or a broken
permission model, and both are things someone should know about.

**JIT platform-operator access is audited most heavily** because it is the access
path with the least natural oversight — nobody at the customer sees it happen, so
the audit trail is the only accountability.

---

## Tamper Evidence

**Decision.** Audit entries are hash-chained per tenant: each entry includes the
hash of the previous entry, making insertion, deletion and modification
detectable.

```
   entry_hash = H( tenant_id ‖ sequence ‖ occurred_at ‖ actor ‖ action
                   ‖ resource ‖ outcome ‖ previous_hash )
```

**Reasoning.** Append-only grants (D-384) prevent the *application* from
modifying audit entries — a compromised application cannot rewrite history. They
do not prevent someone with database administrator access from doing so. The
chain closes that gap: altering an entry breaks every subsequent hash, and the
break is detectable by verification without any external system.

This matters because the audit trail's value is entirely in its
trustworthiness. An audit log that could have been edited by whoever is being
investigated proves nothing.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| **Append-only grants alone** | Prevents application-level tampering; a DBA or a compromised database can still rewrite |
| **Write-once external store** | Strong; adds a second store with its own tenancy and availability problems |
| **External timestamping / notarization** | Strongest — third-party attestation; over-engineered before an enterprise customer requires it. **Chain design deliberately permits adding it later**: periodically publish a chain head. |
| **Blockchain** | No |

**Trade-offs.** Hash computation on every audit write — negligible. Chain
verification is O(n) over the range verified, so it runs as a scheduled job over
recent entries rather than the full history. And the chain is **per tenant**,
which means concurrent writes for one tenant must be serialized to produce a
deterministic sequence — a real constraint, handled by a per-tenant sequence
allocation rather than a lock held across the transaction.

**Benefits.** Tampering is detectable without trusting the database
administrator. The periodic chain head can be published externally later, giving
third-party attestation without redesign.

**Long-term impact.** The property that makes the audit trail usable as evidence
rather than merely as a record.

**Verification** runs nightly over the previous day's entries and on demand for
any range; a broken chain raises a security incident, not a defect ticket.

---

## Temporal State History

Audit records *that* something changed. Some entities also need *what the state
was* at a past moment.

**Decision.** Full temporal history is maintained selectively — for entities
where historical state has business or compliance meaning — not universally.

| Entity | Temporal history | Why |
| --- | --- | --- |
| `ArtifactVersion` | **Inherent** — immutable versions (`32`) | The core product model |
| `Membership`, `RoleAssignment` | **Yes** — validity period | "Who had access on 3 March?" is a real security question |
| `PolicyRule` | **Yes** | Reconstructing why access was granted requires the policy as it was |
| `Plan`, `Subscription`, `Entitlement` | **Yes** | Billing disputes need the terms in force at the time |
| `PricingModel` | **Yes** | Commercial disputes need the price as offered |
| `Task`, `Sprint` | **No** — audit entries suffice | State transitions are audited; full history adds little |
| `FeatureFlag` | **Yes** — cheap and explains behaviour | "Was this enabled on the day of the incident?" |
| Everything else | **No** | Cost without demonstrated need |

**Reasoning.** Temporal history on every table doubles write volume, doubles
storage, and complicates every query. Applied where a question about past state
is genuinely asked, it is invaluable — and the questions above are asked, in
security investigations, billing disputes and incident reviews.

**Mechanism:** validity-period columns (`valid_from`, `valid_to`) with the
current row having an open end, plus a history table for superseded rows. Queries
default to current state; historical queries are explicit.

**Alternatives.** *System-versioned temporal tables* — where the database
supports them natively this is cleaner; PostgreSQL requires extension or trigger
machinery, and triggers hide behaviour from the application (P5). *Reconstruct
from audit* — audit records field names, not values (D-84), so reconstruction is
impossible by design. *Event sourcing* — solves this natively and was rejected
generally (D-38).

---

## Query Patterns

| Question | Served by |
| --- | --- |
| "What did this user do last week?" | Audit, `(tenant_id, actor_id, occurred_at)` |
| "Who accessed this contract?" | Audit, `(tenant_id, resource_type, resource_id, occurred_at)` |
| "Why was this request denied?" | Audit, outcome = denied, plus `reason` |
| "Who had admin on 3 March?" | Temporal membership history |
| "What did this requirement say in version 4?" | `ArtifactVersion` — inherent |
| "What was the price we quoted?" | Temporal pricing history |
| "Has this audit trail been altered?" | Chain verification |
| "Show me everything about this person" (DSAR) | Audit by actor + all owned resources (`48`) |

Indexes per `45`: `tenant_id`-leading composites, plus BRIN on `occurred_at` for
the large append-only table (D-493).

---

## Tenant-Facing Audit Access

**Decision.** Tenant administrators can query their own tenant's audit trail
through the product.

**Reasoning.** Transparency is a feature and a sales asset: customers with their
own compliance obligations can satisfy them without opening a support ticket, and
a platform that shows customers exactly who touched their data earns trust that
one requiring a support request does not.

It also removes a support burden that scales with customer count.

**Constraints:** RLS-scoped to their tenant, of course; platform-internal entries
(JIT operator access to *their* tenant) are visible to them — deliberately, since
concealing our own access would defeat the purpose; and entries about other
tenants are invisible, structurally.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-516 | Audit, events and logs are separate mechanisms with separate stores | Different audiences, retention and integrity guarantees; one mechanism satisfies none well |
| D-517 | Audit is never derived from events or from CDC | Not all audited actions are events; CDC captures row changes rather than intent |
| D-518 | `change_summary` records field names, never values | Values would put commercial and personal data in a 7-year store with different access controls |
| D-519 | Action vocabulary is closed | An open vocabulary makes audit queries unknowable |
| D-520 | Authorization denials are audited; grants are not | Denials are low-volume, high-signal; grants are enormous volume, no forensic value |
| D-521 | Audit entries are hash-chained per tenant | Append-only grants stop the application, not a database administrator |
| D-522 | Chain design permits later external notarization without redesign | Publishing a periodic chain head adds third-party attestation when enterprise demand arrives |
| D-523 | Chain verification runs nightly; a break is a security incident | The trail's value is entirely in its trustworthiness |
| D-524 | Temporal history maintained selectively, not universally | Doubles writes and storage; invaluable only where past state is genuinely queried |
| D-525 | Temporal history via validity periods, not database triggers | Triggers hide behaviour from the application |
| D-526 | Tenant admins can query their own audit trail, including our JIT access | Transparency is a feature; concealing our own access defeats the purpose |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Audit write fails and the action proceeds | Incomplete trail | Audit write is in the same transaction as the action (`43`) — both commit or neither |
| Hash chain sequencing contends under concurrent writes | Write latency for high-activity tenants | Per-tenant sequence allocation rather than a transaction-scoped lock; benchmarked |
| Audit volume grows beyond query practicality | Trail becomes unusable | Time partitioning (D-498); tiering to warm and cold (`46`) |
| An audited action added without an audit call | Silent gap discovered during an investigation | Audit emission at the application layer for a defined action set; reviewed in the security checklist (`16`) |
| Temporal history omitted where later needed | Unanswerable question during a dispute | Entity list reviewed at phase boundaries; adding history later covers only from that point |
| Tenant-facing audit exposes another tenant's entries | Cross-tenant disclosure | RLS plus the isolation suite; audit queries covered by T-1 tests |
| Chain break caused by a bug, not tampering | False security incident | Verification distinguishes structural breaks from missing sequences; investigated before escalation |

## Dependencies

- **Depends on:** security strategy (`09`), entity model (`42`), write path
  (`43`), retention (`46`), indexes (`45`).
- **Depended on by:** GDPR (`48`) — particularly the erasure-versus-immutability
  conflict.

## Future Improvements

- Publish the closed action vocabulary before the first audited action ships.
- Add external notarization of periodic chain heads when an enterprise customer
  requires third-party attestation.
- Add tenant-facing audit export for customers' own compliance reporting.
