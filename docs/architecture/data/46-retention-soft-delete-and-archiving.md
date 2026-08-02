# Retention, Soft Delete and Archiving

## Purpose

Define how data ages and how it goes away: what is soft-deleted versus hard-
deleted, what is archived versus discarded, and how long each class is kept.
These decisions determine storage cost, query performance, and whether the
platform can honour a deletion request — and they are among the most commonly
botched in long-lived systems.

## Scope

**In scope:** soft delete policy and mechanics, tombstones, hard deletion,
retention schedule, storage tiering, archival and restore-from-archive.

**Out of scope:** GDPR obligations and erasure mechanics (`48`), backup and
recovery (`49`), partitioning mechanics (`45`).

---

## Soft Delete

### The position

**Decision.** Soft delete is **not the default**. It is applied only where users
need a recovery window as a product feature, is always paired with a hard-delete
sweeper, and is never used as a developer safety net.

**Reasoning.** Soft-deleting everything is the reflexive choice and it is wrong,
for five compounding reasons:

1. **Every query becomes conditional.** `WHERE deleted_at IS NULL` on every read,
   forever. Forget it once — in a report, a join, a raw query, a new engineer's
   first PR — and deleted data reappears. It is a defect class that never stops
   being possible.
2. **Unique constraints break.** A tenant deletes a project named "Acme
   Redesign" and creates another with the same name; a plain unique constraint
   rejects it because the deleted row still occupies the name.
3. **Referential integrity becomes ambiguous.** May a live row reference a
   soft-deleted one? Both answers cause problems, and the question must be
   answered per relationship rather than once.
4. **It defeats deletion obligations.** Soft-deleted data is still data. A tenant
   or data subject told their data is deleted, whose rows remain fully intact
   with a timestamp set, has not had their request honoured (`48`).
5. **Tables grow without bound.** Deleted rows accumulate forever, degrading
   scans, indexes and backups — paying storage and performance cost for data
   nobody will ever read.

The correct framing: **soft delete is a product feature — a trash can with a
restore button — not an engineering safety net.** When it is used as a safety
net, all five costs are paid and the benefit is imaginary, because nobody ever
actually restores from it.

**Alternatives considered.**

| Alternative | Assessment |
| --- | --- |
| **Soft delete everywhere** | Uniform and simple to state; all five costs above. The common default. |
| **Hard delete everywhere** | No accumulation, no query conditions, no ambiguity; loses the user-facing undo that customers reasonably expect for a document platform |
| **Soft delete with no sweeper** | The worst combination — accumulation plus unhonoured deletion obligations |
| **Selective, always swept** *(chosen)* | Recovery where users need it, genuine deletion everywhere, bounded growth |
| **Event-sourced deletion** | Deletion as an event with state rebuilt; elegant, and rejected with event sourcing generally (D-38) |

**Trade-offs.** Two deletion behaviours means engineers must know which applies —
mitigated by the table below and by repository-level defaults. The sweeper is
additional machinery that must run reliably, because if it stops, we silently
revert to unbounded soft delete.

**Benefits.** Users get undo where they expect it. Deletion obligations are
honourable. Tables stay bounded. Most queries carry no condition at all.

**Long-term impact.** A ten-year-old table where nothing was ever really deleted
is slow, expensive, and a compliance liability. The sweeper is what prevents
that, and it must be treated as production-critical rather than housekeeping.

### Where each applies

| Data | Policy | Recovery window |
| --- | --- | --- |
| Projects | **Soft** | 30 days |
| Artifacts and their versions | **Soft** (whole artifact; versions are never individually deleted) | 30 days |
| Uploaded documents | **Soft** | 30 days |
| Tasks, test cases, defects | **Soft** | 14 days |
| Discovery sessions | **Soft** | 30 days |
| Proposals and contracts | **Soft**, and **never swept while a legal-hold flag is set** | 30 days, or indefinite under hold |
| Memberships | **Hard** — revocation is an event, not a deletion | — |
| Role assignments, policy rules | **Hard** | — |
| Stakeholder grants | **Hard** — expiry and revocation are the model | — |
| Sessions, tokens | **Hard** | — |
| Junction and link tables | **Hard** — cascade with their parent | — |
| Audit entries | **Never deleted** before retention expiry (`47`) | — |
| Outbox, processed events, idempotency keys | **Hard**, by partition drop | — |
| Reference data | **Hard**, with FK protection | — |

**Memberships are hard-deleted deliberately**, and the reasoning generalizes: a
revoked membership is recorded as an *audit entry and an event*
(`MembershipRevoked`), not as a row with a timestamp. Keeping revoked memberships
as soft-deleted rows invites a query that forgets the condition and grants access
to someone who no longer has it — turning a soft-delete convenience into a
privilege escalation.

**Contracts carry a legal-hold flag** because a contract under dispute must not
be swept on schedule. The hold blocks the sweeper and is itself audited.

### Mechanics

| Concern | Approach |
| --- | --- |
| Marker | `deleted_at` timestamp, plus `deleted_by` |
| Default scoping | Repository excludes soft-deleted rows **by construction** — including them requires an explicit, reviewable call |
| Unique constraints | **Partial unique index** `WHERE deleted_at IS NULL` — solves failure 2 |
| Index cost | Partial indexes exclude deleted rows entirely (D-492) — deleted rows cost nothing in the index |
| Cascade | Soft-deleting a parent soft-deletes its owned children in the same transaction |
| Cross-context references | Deletion emits an event; other contexts react (`42`) |
| Restore | Clears `deleted_at` on the parent and its cascade set; audited |
| Sweep | Scheduled job hard-deletes past the window; **monitored and alerted** |

**The default-scoping requirement is what makes soft delete survivable.** If
excluding deleted rows is the default behaviour of the repository, forgetting the
condition is not possible in ordinary code — only a deliberate,
`includeDeleted()`-style call sees them, and that call is greppable and
reviewable.

---

## Tombstones

Hard-deleting a row leaves derived stores unaware that it is gone.

**Decision.** Deletion emits a `Deleted` integration event carrying the entity
identity, and derived stores remove their copies on receipt.

| Derived store | Action on deletion event |
| --- | --- |
| Search index | Remove document |
| Vector index | Remove embeddings |
| Read models | Remove or mark rows |
| Cache | Invalidate keys |
| External integrations | Sync deletion where the mapping supports it |

**Tombstone retention:** the deletion event is retained for the full integration
event window (24 months, D-381), so a projection rebuilt from history applies the
deletion rather than resurrecting the entity. **A rebuild that replays creation
but not deletion recreates deleted data** — a genuine, easily-missed defect that
manifests as data returning from the dead after routine maintenance.

---

## Retention Schedule

Consolidated from `36`, with the archival tier added.

| Data class | Hot | Warm | Cold archive | Then |
| --- | --- | --- | --- | --- |
| Active project artifacts | Life of project | — | — | — |
| Closed project artifacts | 6 months after close | 18 months | Indefinite while tenant active | Deleted with tenant |
| Discovery sessions | 6 months | 18 months | With project | — |
| Proposals and contracts | 12 months | 24 months | **7 years** (commercial record) | Reviewed |
| Audit log | 3 months | 9 months | **7 years** | Deleted |
| Integration events | 3 months | 21 months | — | Deleted |
| Domain events | 90 days | — | — | Deleted |
| Generation records | 3 months | 21 months | — | Deleted |
| Job records | 90 days | — | — | Deleted |
| Telemetry | Traces 14 d · logs 30 d · metrics 13 m | — | — | Deleted |
| Exports | 30 days | — | — | Deleted |
| Backups | 35 days PITR | — | — | Expired |

**Audit and commercial records are kept longest** because they are the two
classes with obligations beyond our own convenience: audit for compliance
evidence (SEC-5), contracts for the commercial and legal record. Everything else
is retained only as long as it is useful.

**Retention is enforced automatically, never manually.** A retention policy
executed by someone remembering to run a script is a retention policy that will
lapse, and the lapse is invisible until an audit finds seven years of data that
should have been deleted after one.

---

## Storage Tiering

**Decision.** Three tiers, with movement driven by age and project lifecycle
rather than by access frequency alone.

| Tier | Location | Access | Cost | Contents |
| --- | --- | --- | --- | --- |
| **Hot** | PostgreSQL primary + replicas | Immediate, indexed | Highest | Active work |
| **Warm** | PostgreSQL partitions on cheaper storage | Immediate, indexed, slower | Medium | Recent history, closed projects |
| **Cold** | Object storage, compressed, columnar | **Restore required — minutes to hours** | Lowest | Long-tail archive |

**Reasoning for lifecycle-driven rather than purely age-driven movement.** A
closed project is cold the day it closes, regardless of age; an active project's
two-year-old requirements are still hot because impact analysis traverses them.
Age alone would archive data that is still traversed and retain data nobody will
open again.

**Alternatives.** *Single tier* — simplest, and storage cost grows linearly with
history forever. *Age-only tiering* — automatable and mis-tiers both directions.
*Access-frequency tiering* — theoretically ideal and requires per-row access
tracking, which costs more than it saves at our volumes.

**Trade-offs.** Cold data requires an explicit restore, which is a user-visible
wait and a support consideration. Tier movement is a background operation that
must be monitored.

**Benefits.** Storage cost stays sub-linear in history ($-3). Hot working set
stays small, which keeps cache effective and indexes resident.

**Long-term impact.** Without tiering, a platform holding a decade of every
customer's project history pays hot-storage prices for data that is opened once a
year. This is a cost curve that only becomes visible after several years, by
which time the migration is large.

### Archival mechanics

```
   Trigger: project closed + 24 months, or tenant inactive + 12 months
        │
   1. Export the artifact set and its graph subgraph to a
      self-describing archive (content + lineage + links + approvals)
        │
   2. Verify the archive: checksum, and a test restore of a sample
        │
   3. Write an ArchiveRecord: what, when, where, checksum, schema version
        │
   4. Replace hot rows with a lightweight stub
      (identity + status = archived + archive reference)
        │
   5. UI shows the project as archived, restorable on request
```

**Stubs remain in PostgreSQL rather than the rows disappearing.** A user browsing
a tenant must still see that the project exists, and traversal from a live
artifact into an archived one must resolve to something meaningful rather than a
dangling reference. The stub is what keeps the graph coherent across the
hot/cold boundary.

**Archives are self-describing.** Each carries its schema version and enough
structure to be interpreted without the application that wrote it — because a
seven-year-old archive will outlive several schema generations, and an archive
that can only be read by code that no longer exists is not an archive.

**Restore is a supported, tested operation** (`49`), not a theoretical
capability. An archival path with no exercised restore is data disposal with
extra steps.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-502 | Soft delete is not the default; applied selectively and always swept | Universal soft delete costs query conditions, broken uniqueness, ambiguous integrity, unhonourable deletion, and unbounded growth |
| D-503 | Soft delete is a product feature, never a developer safety net | As a safety net all costs are paid and nobody ever restores |
| D-504 | Repositories exclude soft-deleted rows by construction | Makes forgetting the condition impossible in ordinary code |
| D-505 | Partial unique indexes `WHERE deleted_at IS NULL` | Otherwise deleted rows permanently occupy unique values |
| D-506 | Memberships and grants are hard-deleted; revocation is an event | A forgotten condition on soft-deleted memberships is privilege escalation |
| D-507 | Contracts carry an audited legal-hold flag that blocks the sweeper | A disputed contract must not be swept on schedule |
| D-508 | The hard-delete sweeper is production-critical, monitored and alerted | If it stops, we silently revert to unbounded soft delete |
| D-509 | Deletion emits tombstone events; derived stores remove their copies | Hard deletion otherwise leaves derived stores holding deleted data |
| D-510 | Tombstones retained for the full event window | A rebuild replaying creation without deletion resurrects deleted data |
| D-511 | Retention enforced automatically, never by manual process | A remembered process lapses invisibly |
| D-512 | Tiering driven by project lifecycle, not age alone | Age mis-tiers in both directions; closed projects are cold immediately |
| D-513 | Archived entities leave a stub in PostgreSQL | Keeps browsing and graph traversal coherent across the hot/cold boundary |
| D-514 | Archives are self-describing with an embedded schema version | A seven-year archive outlives the code that wrote it |
| D-515 | Restore-from-archive is a tested operation, not a theoretical one | An unexercised restore path is data disposal with extra steps |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Sweeper fails silently | Unbounded growth; unhonoured deletion obligations | Monitored and alerted; sweep volume tracked as a metric |
| A query omits the soft-delete condition | Deleted data reappears | Repository default scoping; explicit opt-in is greppable |
| Soft delete added to a new table by reflex | The costs return one table at a time | Policy table is the reference; new tables declare their deletion policy |
| Tombstone not emitted on a deletion path | Derived stores retain deleted data | Deletion emits events by construction in the repository layer |
| Projection rebuild resurrects deleted entities | Data returns from the dead after maintenance | Tombstone retention matched to event retention; rebuild tested with deletions |
| Archive written but never restore-tested | Silent data disposal | Sample restore verification at archive time; periodic full restore drill |
| Legal hold not applied before a sweep | Evidence destroyed | Hold flag checked by the sweeper; hold changes audited |
| Cold restore latency surprises users | Support burden | Archived status shown explicitly in the UI with expected restore time |

## Dependencies

- **Depends on:** data and storage (`36`), entity model (`42`), data flow (`44`),
  indexes and partitioning (`45`).
- **Depended on by:** GDPR (`48`), backup and recovery (`49`), audit (`47`).

## Future Improvements

- Add sweep-volume and archive-volume dashboards so both are visible rather than
  assumed to be running.
- Automate periodic restore-from-archive verification alongside backup restore
  drills (D-161).
- Model long-term storage cost per tenant once real retention curves exist; the
  tiering thresholds are estimates.
