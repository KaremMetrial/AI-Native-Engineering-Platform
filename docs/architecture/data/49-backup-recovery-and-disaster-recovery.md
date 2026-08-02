# Backup, Recovery and Disaster Recovery

## Purpose

Specify how data survives failure, corruption, accident and attack — per store,
per scenario, with the procedures that make recovery a rehearsed operation
rather than an improvisation. `15` covers deployment-level disaster recovery;
this document is data-specific and covers the scenarios that infrastructure
failover does not address.

## Scope

**In scope:** backup topology per store, RPO/RTO per data class, recovery
scenario procedures, tenant-scoped restore, backup security and immutability,
and verification.

**Out of scope:** infrastructure DR topology (`15`), retention and archiving
(`46`).

---

## Principles

1. **A backup is not a backup until it has been restored.** Untested backups are
   assumptions (D-161). Verification is the requirement; the backup configuration
   is merely its precondition.
2. **Backups are a target.** An attacker with production access will delete or
   encrypt them. Backup immutability is a security control, not an operational
   nicety.
3. **Derived data is rebuilt, not restored** (D-456). Restoring an index risks
   reintroducing state that diverges from its source.
4. **Recovery granularity must match failure granularity.** Losing one tenant's
   data must not require a decision that affects all tenants.

---

## Backup Topology per Store

| Store | Mechanism | RPO | Retention | Verified by |
| --- | --- | --- | --- | --- |
| **PostgreSQL** | Continuous WAL archiving + daily base backup (PITR) | **≤ 15 min** (A-6) | 35 days | Quarterly full restore drill |
| **Object storage** | Versioning + cross-region replication | Near-zero | 35-day version retention | Quarterly sample restore |
| **Secret store** | Managed backup, separate credential domain | Daily | 90 days | Annual |
| **Redis — cache** | **None** | N/A | — | Rebuild by cache miss |
| **Redis — queue** | AOF persistence | Seconds | — | Failover test |
| **Redis — pub/sub** | **None** | N/A | — | Reconnection |
| **Search index** | **None — rebuilt** | N/A | — | Scheduled rebuild exercise |
| **Vector index** | **None — rebuilt** | N/A | — | Scheduled rebuild exercise |
| **Read models** | **None — rebuilt from events** | N/A | — | Blue-green rebuild (`43`) |
| **Cold archive** | Object storage, versioned, replicated | Near-zero | Per retention (`46`) | Sample restore at write + periodic |
| **Telemetry** | Backend-managed | Best effort | Per tier | Not restored — accepted loss |

**Four stores have no backups, deliberately.** The cache, pub/sub, search and
vector indexes and read models are all derived (D-382) and are rebuilt rather than
restored. Backing them up would add cost, add a restore path that could
reintroduce divergent state, and give false assurance that they are
authoritative when they are not.

**The queue is the exception among Redis instances** — jobs in flight are not
derivable from anything, which is why that instance alone carries persistence and
`noeviction` (D-308).

---

## RPO and RTO by Data Class

| Data class | RPO | RTO | Basis |
| --- | --- | --- | --- |
| Artifacts, versions, graph, approvals | 15 min → 5 min (Ph4) | 4 h → 1 h | A-6, A-7 |
| Identity, memberships, grants | 15 min | 1 h | Access must be restored first |
| Audit log | **Zero tolerance for silent loss** | 4 h | Compliance evidence (`47`) |
| Commercial — proposals, contracts | 15 min | 4 h | Contractual record |
| Queued jobs | Seconds | Minutes | AOF; idempotency covers redelivery |
| Blob content | Near-zero | 1 h | Versioning |
| Derived stores | N/A | Hours (rebuild) | Recomputable |
| Telemetry | Best effort | N/A | Visibility, not function |

**Identity has a shorter RTO than the data it protects**, deliberately: restoring
artifacts before anyone can authenticate is useless, so identity comes back
first. This ordering is written into the recovery runbook rather than discovered
during an incident.

**Audit's "zero tolerance for silent loss"** is different from a numeric RPO: a
gap in a hash-chained trail is *detectable* (`47`), so a loss is known rather
than silent. Detectability is the requirement — an audit gap we know about is
recoverable as a documented incident; one we do not know about undermines the
entire trail.

---

## Recovery Scenarios

Each scenario has a distinct procedure. Enumerating them in advance is what makes
recovery a decision tree rather than an improvisation under pressure.

| Scenario | Procedure | Target |
| --- | --- | --- |
| **Instance or AZ failure** | Automatic standby promotion | < 5 min, automatic |
| **Accidental row deletion by a user** | Soft-delete restore (`46`) — no backup involved | Immediate, self-service |
| **Accidental hard deletion by a bug** | PITR to a staging instance; extract and re-import affected rows | Hours |
| **Logical corruption from a deploy** | Identify the deploy window; PITR to just before; replay legitimate writes from events where possible | Hours |
| **Single-tenant data loss** | **Tenant-scoped restore** (below) | Hours |
| **Derived store corruption** | Rebuild from source (D-456) | Hours, no data loss |
| **Blob deletion** | Object version restore | Minutes |
| **Queue loss** | Jobs lost; **reconcile from job records** and re-enqueue incomplete work | Hours |
| **Full region loss** | Rebuild from IaC + cross-region backups | Best effort Ph1, improving Ph4 |
| **Ransomware / malicious deletion** | Restore from immutable backups (below) | Hours to days |

**The queue-loss row deserves attention** because it is the one where backups do
not fully save us: AOF gives seconds of RPO, but any loss means in-flight jobs
vanish. Recovery is *reconciliation* — `job_records` in PostgreSQL record what
was enqueued and what completed, so incomplete work is identifiable and
re-enqueueable. **This is why job state lives in PostgreSQL rather than only in
the queue**: the queue is transport, the database is the record.

---

## Tenant-Scoped Restore

The scenario generic backup strategies handle worst, and the direct operational
cost of the shared-database tenancy model (`07`).

```
   1. Identify: tenant, time range, affected entities
   2. Provision an isolated restore instance
   3. PITR the full database to that instance at the target timestamp
   4. Extract the tenant's rows — every owning context, in FK dependency order
   5. Validate: row counts, referential integrity, tenant_id uniformity
   6. Re-import into production within a transaction, resolving conflicts
      against rows written since the restore point
   7. Rebuild that tenant's derived data — search, vectors, projections
   8. Verify with the tenant
   9. Audit the entire operation
```

**Why not simply restore the database.** A full restore to a point in time
discards every *other* tenant's writes since that point. Recovering one tenant by
destroying fifty thousand tenants' recent work is not a recovery.

**The conflict-resolution step is the difficult one.** If the tenant continued
working after the loss, restored rows may conflict with newer ones. The policy:
**newer production rows win by default; restored rows fill gaps only** — and any
genuine conflict is surfaced to the tenant rather than resolved silently. Silent
resolution of a data conflict is how a recovery becomes a second data loss.

**Step 7 is frequently forgotten.** Restoring rows without rebuilding that
tenant's search index, embeddings and projections leaves the tenant with data
they cannot find and AI grounding that does not include it.

**This procedure is rehearsed quarterly**, not documented and hoped for — it has
enough steps that first execution during a real incident would be slow and
error-prone.

---

## Backup Security and Immutability

**Decision.** Backups are encrypted, stored under a **separate credential
domain** from production, and retained in an **immutable, write-once** form for a
minimum window.

**Reasoning.** Most backup strategies implicitly assume the failure is accidental.
An attacker who compromises production credentials will look for backups and
delete or encrypt them — that is standard ransomware procedure, and a backup
deletable with production credentials provides no protection against the scenario
most likely to need it.

Immutable retention means backups within the window **cannot be deleted or
modified by anyone**, including us, including with valid credentials. That is the
property that makes them survive a credential compromise.

**Alternatives.** *Standard backups with access control* — adequate for
accidental loss, useless against a credential compromise. *Offline backups* —
maximum protection, impractical operational cadence at our RPO. *Immutable
object-lock retention* *(chosen)* — protection against deletion with a manageable
operational model.

**Trade-offs.** Immutable backups cannot be deleted early even when we want them
gone — which interacts with GDPR erasure, and is precisely why crypto-shredding
(D-530, D-532) is the erasure mechanism: encrypted data in an undeletable backup
is unrecoverable once the key is destroyed. **The two decisions are
complementary, and neither works alone.**

Immutable retention also costs storage that cannot be reclaimed early.

**Additional controls:** backups encrypted with keys distinct from production
data keys; restore requires step-up authentication and is audited; backup access
is monitored, with a listing or bulk-read alerting as a potential precursor to
exfiltration.

---

## Verification

| Verification | Cadence | Failure means |
| --- | --- | --- |
| Automated restore of the latest base backup to a scratch instance | **Weekly** | Backups are not usable — critical incident |
| Full recovery drill with timing against RTO | Quarterly | RTO target is not met — plan revision |
| **Tenant-scoped restore rehearsal** | Quarterly | The procedure does not work — revise before it is needed |
| Derived store rebuild exercise | Quarterly | Rebuild path broken — a "recoverable" store is not |
| Archive sample restore | Quarterly | Archives unreadable — silent data disposal (`46`) |
| Cross-region failover | Annually | Regional DR is theoretical |
| Backup immutability verification | Quarterly | Attempt deletion; it must fail |

**Weekly automated restore is the highest-value item here.** Most backup failures
are discovered during the first real restore — a corrupted chain, a missing WAL
segment, a configuration change that silently stopped archiving. Weekly automated
verification finds those within days rather than during an incident.

**The immutability verification is deliberately a deletion attempt**: the control
is only proven by trying to violate it and failing. A configuration that *claims*
immutability but does not enforce it looks identical to one that does, until it
matters.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-538 | Derived stores are never backed up; they are rebuilt | Restoring risks reintroducing divergent state and falsely implies authority |
| D-539 | Job state lives in PostgreSQL, not only in the queue | The queue is transport; queue loss is then recoverable by reconciliation |
| D-540 | Identity is restored before the data it protects | Restoring artifacts nobody can authenticate to reach is useless |
| D-541 | Audit loss must be detectable rather than merely bounded | A known gap is a documented incident; an unknown gap undermines the whole trail |
| D-542 | Tenant-scoped restore via an isolated instance, never a full production restore | Recovering one tenant by discarding all others' work is not a recovery |
| D-543 | Restore conflicts resolved in favour of newer production rows; genuine conflicts surfaced | Silent conflict resolution turns a recovery into a second data loss |
| D-544 | Tenant restore includes rebuilding that tenant's derived data | Otherwise the tenant has data they cannot find and AI cannot ground on |
| D-545 | **Backups are immutable and write-once for a minimum window** | A backup deletable with production credentials does not protect against the scenario most likely to need it |
| D-546 | Backup credentials are a separate domain from production | Production compromise must not confer backup access |
| D-547 | Immutable backups and crypto-shredding are complementary and both required | Erasure in undeletable backups is achievable only by key destruction |
| D-548 | Weekly automated restore verification, not only quarterly drills | Most backup failures are silent and are found only by restoring |
| D-549 | Immutability verified by attempting deletion | A control that claims enforcement looks identical to one that enforces, until it matters |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Backup chain silently broken | Unrecoverable data loss | Weekly automated restore; alerting on WAL archiving gaps |
| RTO exceeded during a real incident | A-7 breached; extended outage | Quarterly drills with recorded timings; procedure revised when missed |
| Tenant-scoped restore first attempted during an incident | Slow, error-prone recovery | Rehearsed quarterly |
| Derived rebuild path broken and unnoticed | A "recoverable" store is not | Quarterly rebuild exercise |
| Ransomware deletes or encrypts backups | Total loss | Immutable retention; separate credential domain; access alerting |
| Immutable retention conflicts with an erasure request | Compliance failure | Crypto-shredding is the erasure mechanism (D-530) |
| Restore reintroduces data a subject asked to erase | Compliance failure | Erasure re-applied post-restore as a mandatory procedure step |
| Cross-region recovery never exercised | Regional DR is theoretical | Annual failover test; honest communication of Phase 1 limits |

## Dependencies

- **Depends on:** deployment strategy (`15`), data and storage (`36`), retention
  and archiving (`46`), audit (`47`), GDPR (`48`).
- **Depended on by:** operational runbooks; the recovery scenarios in `41`.

## Future Improvements

- Automate tenant-scoped restore so it is a tool rather than a nine-step manual
  procedure — the step count is itself a risk.
- Add continuous backup-integrity verification beyond the weekly restore.
- Design cross-region recovery ahead of Phase 4 residency work, reusing the same
  regional infrastructure.
- Add post-restore erasure re-application as an automated step rather than a
  procedural one.
