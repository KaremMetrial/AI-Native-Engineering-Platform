# GDPR and Data Protection Architecture

## Purpose

Express data protection obligations as data architecture: where personal data
lives, who controls it, how subject rights are fulfilled, and how the right to
erasure is reconciled with an immutable audit trail — the hardest conflict in
this document, and the one most systems handle badly.

`09` states the compliance posture; this specifies the data structures and
mechanisms that make it achievable.

## Scope

**In scope:** controller/processor split, personal data classification and
localization, lawful basis, subject rights mechanics, erasure versus
immutability, minimization, portability and residency.

**Out of scope:** legal advice, security controls (`09`, `39`), retention
mechanics (`46`).

---

## Controller and Processor — the Decision That Shapes Everything

**Decision.** We are the **controller** for account and platform data, and the
**processor** for tenant content. Subject requests are routed accordingly.

| Data | Role | Subject request handled by |
| --- | --- | --- |
| Tenant admin and user account data — name, email, credentials, sessions | **Controller** | Us, directly |
| Platform usage telemetry tied to a user | **Controller** | Us, directly |
| Billing contact and payment data | **Controller** | Us, directly |
| **Tenant project content** — requirements, discovery notes, uploaded briefs, client stakeholder details | **Processor** | **The tenant**, with our tooling |
| Client stakeholder identities inside a tenant's projects | **Processor** | The tenant |

**Reasoning.** An agency using the platform decides what client data to put into
it and why; we merely store and process it on their instruction. That makes them
the controller and us the processor for that content — and it means **a data
subject in their client's organization must direct their request to the agency,
not to us.** Fulfilling it directly would be acting outside our instructions and
would deprive the controller of a decision that is legally theirs.

Getting this wrong in either direction is a compliance failure: acting as
controller for tenant content oversteps; acting as processor for our own account
data would leave subject requests unfulfilled.

**Alternatives.** *Controller for everything* — simpler, and wrong; we have no
lawful basis to decide the purposes of a client's brief. *Processor for
everything* — leaves our own user accounts without a controller. *Joint
controller* — appropriate for some arrangements and adds a shared-responsibility
agreement with every tenant, disproportionate to the relationship.

**Trade-offs.** Two request paths and two sets of tooling. Tenants need
self-service tooling to fulfil requests we cannot fulfil for them, which is
product work driven by a legal distinction.

**Benefits.** Correct legal posture. Clear customer conversation: "here is what
you handle, here is what we handle, here are the tools."

**Long-term impact.** This distinction determines the shape of the DSAR tooling
and cannot be retrofitted cheaply — the tooling is different for each role.

---

## Personal Data Classification and Localization

**Decision.** Personal data is **localized** — confined to a small, enumerated
set of tables and columns — and never denormalized into other tables for
convenience.

**Reasoning, and this is the highest-leverage decision in this document.**
Denormalizing a user's name into every table that displays it — `created_by_name`
on artifacts, tasks, comments, approvals — is a natural performance instinct and
it makes erasure nearly impossible. Every such copy is a place personal data must
be found and removed, across dozens of tables, some of them archived, some
projected into read models, some exported.

Localizing personal data means erasure touches a known, small set of locations.
It converts an unbounded search problem into a bounded operation.

This connects directly to D-473: write-side denormalization is permitted only for
invariant enforcement, never for display convenience. That rule exists partly for
model cleanliness and mostly for this.

**Alternatives.** *Denormalize freely for read performance* — faster reads, and
erasure becomes an archaeology exercise with no way to prove completeness.
*Encrypt personal data everywhere it appears* — strong, and key management across
scattered copies is worse than not scattering them. *Tokenize at write* — every
personal reference becomes an opaque token resolved at read; effectively what
localization achieves, with more machinery.

**Trade-offs.** Displaying "approved by Jane Smith" requires a join or a lookup
rather than a stored string. Mitigated by caching the small identity dataset
(`37`), which is exactly the kind of data caching serves well.

**Benefits.** Erasure is bounded and provable. DSAR export is assemblable from
known locations. The blast radius of an identity data breach is one table set.

**Long-term impact.** A ten-year-old system with names denormalized across forty
tables cannot honestly claim to fulfil erasure requests. Localization is
effectively impossible to retrofit, because finding every copy is the problem.

### The personal data map

| Category | Location | Controller/Processor | Lawful basis |
| --- | --- | --- | --- |
| **Account identity** — name, email, avatar | `users`, `user_profiles` | Controller | Contract |
| **Authentication** — credentials, MFA, sessions | `credentials`, `sessions` | Controller | Contract |
| **Membership** — tenant, role, validity | `memberships` (+ temporal) | Controller | Contract |
| **Billing contact** | `billing_contacts` | Controller | Contract / legal obligation |
| **Usage telemetry** — actor-attributed actions | `audit_log`, telemetry | Controller | Legitimate interest (security, service integrity) |
| **External stakeholder identity** | `stakeholder_identities` | **Processor** | Tenant's basis |
| **Personal data inside tenant content** | `artifact_versions`, uploads, discovery responses | **Processor** | Tenant's basis |
| **Derived** — projections, search, embeddings | Derived stores | Inherits source | Inherits source |

**The last two rows are the difficult ones.** Personal data inside tenant content
is unstructured and unpredictable — a client brief may name individuals anywhere
in its prose. It cannot be localized, because we do not control its structure.

**How that is handled honestly:** we do not attempt to locate individuals inside
tenant content automatically. The **tenant is the controller** and decides what
their content contains; our obligation as processor is to delete or export
content on their instruction, at artifact granularity, and to make that operation
complete and verifiable. Claiming to find every mention of a person inside
free-form documents would be a promise we could not keep.

---

## Subject Rights Mechanics

| Right | Mechanism | Target |
| --- | --- | --- |
| **Access** | Assemble from the personal data map | 30 days |
| **Rectification** | Self-service for account data; tenant-managed for content | Immediate |
| **Erasure** | Below — the hard case | 30 days |
| **Portability** | Machine-readable export of controller-held data | 30 days |
| **Restriction** | Account flag suspending processing while retaining data | Immediate |
| **Objection** | Applies to legitimate-interest processing (telemetry) | 30 days |

**Access and portability are assembled from the map**, not from a search. A
request runs a defined query set against enumerated locations — which is only
possible because of localization.

---

## Erasure Versus Immutability — the Central Conflict

The genuine architectural problem: **GDPR grants a right to erasure; the audit
trail must be immutable and retained for seven years** (`47`). Both cannot be
satisfied naively.

**Decision.** **Crypto-shredding** for personal data in immutable records:
personal fields are encrypted with a per-subject key; erasure destroys the key,
rendering the data permanently unrecoverable while the record's structure,
hashes and chain remain intact.

```
   Audit entry (immutable, hash-chained)
   ┌──────────────────────────────────────────────┐
   │ id, tenant_id, occurred_at, action,          │  ← plaintext, no personal data
   │ resource_type, resource_id, outcome,         │
   │ sequence, previous_hash, entry_hash          │
   ├──────────────────────────────────────────────┤
   │ actor_ref  → subject_id (pseudonymous)       │  ← stable pseudonym
   │ actor_details → ENCRYPTED with subject key   │  ← name, email, IP
   └──────────────────────────────────────────────┘

   Erasure: destroy the subject key
        │
        ├─ actor_details becomes permanently unrecoverable
        ├─ entry_hash unchanged → chain intact, verification passes
        └─ the audit fact survives: "subject 7f3a approved contract X on 3 March"
```

**Reasoning.** This satisfies both obligations honestly. The personal data is
genuinely irrecoverable — destroying the key is not obfuscation, it is
destruction, and this is the accepted approach among regulators for exactly this
conflict. Meanwhile the audit record retains its evidentiary structure: the
compliance fact that an approval occurred, by a distinct actor, at a time, is
preserved without identifying who.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| **Delete audit entries** | Destroys the audit trail's integrity and breaks the hash chain — and audit retention is itself a legal obligation |
| **Overwrite personal fields in place** | Breaks immutability and the hash chain; also means the application can modify audit records, which defeats their purpose |
| **Refuse erasure, citing legal obligation** | Legitimate for genuinely mandated records and over-broad as a blanket answer; most of what we hold is not legally mandated |
| **Pseudonymize only, retain the mapping** | The mapping is personal data — erasure not achieved, merely relocated |
| **Crypto-shred** *(chosen)* | Both obligations satisfied; structure preserved; destruction genuine |

**Trade-offs, stated plainly:**

- **Key management becomes critical.** A per-subject key that is lost
  accidentally erases data that should have been retained; one that is not
  destroyed reliably means erasure did not occur. This is real operational
  weight, and it is the cost of the approach.
- **Encrypted fields are not queryable.** Audit queries by actor use the
  pseudonymous `subject_id`, not the name — which is sufficient for forensic use
  and does mean an auditor cannot search by name for an erased subject. Correct.
- **Backups.** A backup taken before key destruction contains the encrypted data
  — and without the key it is equally unrecoverable. **Crypto-shredding solves
  the backup problem that plain deletion cannot** (`36`, D-398), which is a
  significant secondary benefit.

**Benefits.** Erasure is genuine and provable. Audit integrity is preserved.
Backups do not undermine erasure.

**Long-term impact.** This is the mechanism that lets an immutable,
seven-year-retained audit trail coexist with subject rights for a decade. Without
it, the two requirements are in permanent unresolved tension.

### Where each approach applies

| Data | Erasure approach |
| --- | --- |
| Account identity, credentials, profile | **Hard delete** — no retention obligation |
| Sessions, tokens | Hard delete |
| Audit entries | **Crypto-shred the personal fields**; structure and chain retained |
| Temporal membership history | Crypto-shred personal fields; validity periods retained |
| Telemetry | Delete tenant/actor-scoped records, or documented expiry within retention |
| Billing records | **Retained under legal obligation** (tax/accounting), personal fields minimized |
| Derived stores | Deleted with source via tombstones (`46`) |
| Tenant content | **Tenant's decision as controller** — we delete at their instruction, at artifact granularity |

---

## Data Minimization

Expressed in the schema rather than as a policy statement:

| Practice | Applied |
| --- | --- |
| Collect only what a feature requires | New personal data fields require justification in review |
| No denormalized personal data (D-473) | Enforced in review; localization is the rule |
| Telemetry carries identifiers, never content (D-304) | Redacting logger; collector pass |
| Audit records field names, never values (D-518) | Structural |
| No card data — payment provider tokens only | Why PCI is out of scope (D-87) |
| Pseudonymous identifiers in derived stores wherever sufficient | Reduces personal data surface |
| Retention limits per class (`46`) | Automated, not manual |

**Retention limits are a GDPR requirement, not merely an operational one** —
storage limitation is a principle, so keeping data indefinitely because storage
is cheap is a compliance failure independent of any cost consideration.

---

## Residency and Sub-Processors

**Residency (C-4).** Tenant-selectable EU or US regions, Phase 4. Requires
regional deployment, not merely routing — data at rest, backups, derived stores
and telemetry must all stay in region. Recorded here because the tenancy model
(`07`) already supports tenant-to-infrastructure routing, which is what makes
this achievable without an application rewrite.

**Sub-processors.** Every external system receiving personal data (`29`) is a
sub-processor requiring a register entry, a data processing agreement, and tenant
notification of changes. Model providers are the most significant, which is why
contractual no-training (C-3) is a sales blocker rather than a nicety.

**Records of processing (C-7)** are derived from the personal data map and the
perimeter table in `29`, so they stay current with the architecture rather than
being maintained separately and drifting.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-527 | Controller for account data, processor for tenant content | An agency decides what client data enters the platform; that decision is theirs, and so is the subject request |
| D-528 | **Personal data is localized to enumerated tables; never denormalized** | Converts erasure from an unbounded search into a bounded operation; effectively impossible to retrofit |
| D-529 | We do not attempt to locate individuals inside free-form tenant content | The tenant is controller; claiming to find every mention would be a promise we could not keep |
| D-530 | **Crypto-shredding for personal data in immutable records** | Satisfies erasure and audit immutability simultaneously; destruction is genuine |
| D-531 | Audit entries carry a stable pseudonymous `subject_id` plus encrypted details | Preserves the compliance fact without preserving identity after erasure |
| D-532 | Crypto-shredding also resolves the backup erasure problem | Encrypted data in a backup is unrecoverable without the key |
| D-533 | Per-subject key lifecycle is treated as production-critical | Lost keys erase retained data; undestroyed keys mean erasure did not occur |
| D-534 | Hard delete where no retention obligation exists | Crypto-shredding is for immutable records only, not a universal substitute |
| D-535 | Billing records retained under legal obligation, personal fields minimized | Legal obligation is a valid basis for retention; minimization still applies |
| D-536 | Retention limits enforced as a GDPR principle, not only for cost | Storage limitation is a compliance requirement independent of cost |
| D-537 | Records of processing derived from the personal data map and perimeter table | A separately maintained register drifts from the architecture |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Personal data denormalized by a well-meaning optimization | Erasure becomes unprovable | Review rule (D-473); personal data map is the reference; new personal fields justified |
| Per-subject key destroyed in error | Irrecoverable loss of data that should have been retained | Key destruction is a two-step audited operation with a delay window |
| Per-subject key not destroyed on erasure | Erasure not achieved; compliance failure | Erasure job verifies key destruction and records it in the audit trail |
| Subject request misrouted between controller and processor | Unfulfilled request or overstepping | Routing rules documented and built into the request intake flow |
| Tenant lacks tooling to fulfil their processor-side obligations | Customer compliance failure attributed to us | Tenant-facing export and deletion tooling is a Phase 2 requirement (T-4, T-5) |
| Derived stores retain personal data after erasure | Compliance failure | Tombstones (D-509); per-store deletion verification (`36`) |
| Residency promised before regional deployment exists | Contractual breach | C-4 is Phase 4; not offered before then |

## Dependencies

- **Depends on:** security strategy (`09`), tenancy (`07`), system context
  (`29`), entity model (`42`), retention (`46`), audit (`47`).
- **Depended on by:** backup and recovery (`49`).

## Future Improvements

- Publish the personal data map as a generated artifact from schema annotations,
  so it cannot drift from reality.
- Build tenant-facing DSAR tooling in Phase 2 alongside tenant export (T-4).
- Complete a formal DPIA before Phase 1 launch, as C-1 requires.
- Design regional deployment ahead of Phase 4 rather than at it.
