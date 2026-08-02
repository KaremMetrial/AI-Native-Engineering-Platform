# Data Flow Architecture

## Purpose

Trace data through its complete lifecycle — from the moment it enters the
platform to the moment it is verifiably gone. Component and container documents
describe *where things run*; this document describes *where data goes*, which is
the view that makes retention, isolation and compliance obligations concrete.

## Scope

**In scope:** the lifecycle stages, ingestion paths, the write and derivation
flows, serving paths, export, aging and deletion; plus worked flows for the three
data journeys that matter most.

**Out of scope:** entity structure (`42`), read model mechanics (`43`), retention
policy detail (`46`).

---

## Lifecycle Stages

Every piece of data in the platform passes through some subset of these stages.
Naming them makes it possible to ask, of any data class, "which stages apply, and
what governs each?"

```
   1 INGEST ──▶ 2 VALIDATE ──▶ 3 PERSIST ──▶ 4 DERIVE ──▶ 5 SERVE
                                                 │            │
                                                 │            ▼
                                                 │        6 EXPORT
                                                 ▼
                                            7 AGE ──▶ 8 DELETE
```

| Stage | Governs |
| --- | --- |
| 1 · Ingest | Trust classification, size limits, provenance |
| 2 · Validate | Structural validation, domain invariants, malware scanning |
| 3 · Persist | Tenant scoping, transaction boundary, outbox, audit |
| 4 · Derive | Projections, search, embeddings — all rebuildable (D-382) |
| 5 · Serve | Consistency choice, authorization, caching |
| 6 · Export | Portability, perimeter crossing, signed access |
| 7 · Age | Hot → warm → cold transitions (`46`) |
| 8 · Delete | Soft, hard, and verification across every store (`46`) |

---

## Stage 1 · Ingestion

**Decision.** Every ingestion path assigns a **trust classification** and a
**provenance record** at the boundary, and both travel with the data thereafter.

| Path | Trust | Provenance recorded |
| --- | --- | --- |
| Direct user input (forms, editors) | Semi-trusted — authenticated but user-supplied | actor, tenant, timestamp |
| Uploaded documents | **Untrusted** | actor, tenant, filename, content hash, scan result |
| Integration sync (Git, issue trackers) | **Untrusted** — external system output | connection, external ID, sync run |
| Inbound webhooks | **Untrusted** — signature-verified but externally authored | source, signature validation, receipt time |
| AI generation | **Semi-trusted, draft only** | model, prompt version, input versions, cost |
| System-generated (defaults, migrations) | Trusted | job, deploy version |

**Reasoning.** Trust and provenance assigned at the boundary and carried onward
is what makes the untrusted-content path in `39` enforceable. If classification
happens later — at the point of use — then each point of use must re-derive it,
and one that forgets treats hostile content as trusted.

This also makes lineage possible: the requirement that a generated artifact be
explicable years later (U-4) depends on provenance captured at ingestion, because
it cannot be reconstructed afterwards.

**Alternatives.** *Classify at point of use* — flexible, and duplicates the
decision at every consumer. *Trust everything post-authentication* — the common
default; authentication proves who sent it, never that its content is safe.

**Trade-offs.** Provenance metadata on every ingested item — storage overhead,
and a field that must be threaded through every path.

**Benefits.** The untrusted path is enforceable rather than aspirational.
Lineage is complete. Any datum can answer "where did you come from?"

**Long-term impact.** Provenance cannot be backfilled. Data ingested without it
is permanently unexplainable, and in a platform selling traceability that is a
product defect, not merely a data gap.

---

## Stage 3 · The Persist Flow

The single transaction that anchors everything downstream (`43`):

```
   ┌──────────────── ONE TRANSACTION ─────────────────┐
   │  write aggregate state    (normalized tables)     │
   │  increment version        (optimistic locking)    │
   │  append audit entry       (`47`)                  │
   │  insert outbox events     (`35`)                  │
   └────────────────────┬──────────────────────────────┘
                        │ commit — atomic
                        ▼
              everything downstream follows
```

**Nothing leaves this transaction except through the outbox.** No direct
publish, no synchronous call to another store, no cache write before commit. Any
of those is a dual write, and a dual write is a silent divergence (D-371).

---

## Stage 4 · The Derivation Flow

Derived data is built from events, never written directly by the producer.

```
   Outbox relay
        │
        ├──▶ Read model projections (`43`)     lag 5 s – 15 min
        │
        ├──▶ Search index                      lag 60 s
        │
        ├──▶ Vector embeddings                 lag 5 min
        │      └── APPROVED versions only (D-446)
        │
        ├──▶ Cache invalidation (`37`)         lag < 1 s
        │
        └──▶ Notifications, integrations       lag seconds
```

**Why derivation is event-driven rather than written inline.** Writing the search
index inside the persist transaction would make search availability a
prerequisite for saving a requirement — coupling a critical write path to a
degradable dependency (`29`). Event-driven derivation means the search index can
be down, rebuilt, or replaced without any write path knowing.

**All derived stores are rebuildable and none is authoritative** (D-382). The
consequence worth stating: **a bug in a projection is never data loss.** It is
recomputable. This is what makes derived data safe to iterate on.

---

## Stage 5 · Serving

| Read | Source | Consistency | Cached |
| --- | --- | --- | --- |
| Aggregate detail / edit | Primary | Strong | No |
| Approval flow | Primary | Strong | No |
| Listings, browse | Replica | Seconds | Short TTL |
| Cross-context views | Read model | Per budget (`43`) | Short TTL |
| Full-text search | Search index | ~60 s | No |
| Semantic retrieval | Vector index | ~5 min | No |
| Graph traversal | Primary or replica | Strong or seconds | Event-invalidated |
| Permissions | Cache | **Event-invalidated** (D-401) | Yes, with TTL backstop |
| AI generation context | Primary, approved versions only | Strong | Content-addressed |

**Permissions are the row where the caching decision is a security decision**
(`37`): TTL-only invalidation would serve revoked access until expiry.

**AI generation context reads the primary** despite being an asynchronous path,
because grounding on stale or unapproved content compounds error through the
graph (D-91).

---

## Stage 6 · Export

Data leaving the platform, deliberately enumerated because each is a disclosure.

| Export | Trigger | Contains | Control |
| --- | --- | --- | --- |
| Document export (PDF, DOCX) | User action | One artifact or set | Authorization; signed URL; 30-day expiry |
| Tenant data export (T-4) | Tenant admin | Complete tenant data, machine-readable | Step-up auth; audited; long-running job |
| Data subject export (GDPR) | DSAR (`48`) | One person's data | Verified identity; audited |
| Integration sync outbound | Continuous | Mapped subset | Per-connection scope; anticorruption layer |
| Analytics to observability | Continuous | **Identifiers only, never content** (D-304) | Redaction at emission and at collector |
| AI provider context | Per generation | Assembled grounding context | Tenant-scoped; contractual no-training (C-3) |

**Tenant data export is an anti-lock-in commitment as much as a compliance
one** (`07`): customers commit more readily to a platform they can leave, and
the export must therefore be genuinely complete rather than technically
compliant.

---

## Worked Flow · A Requirement's Life

```
  DISCOVERY                                    the raw input
    User conducts a discovery session
    → DiscoverySession, Response rows          [Discovery context]
    → uploaded brief → object storage          [UNTRUSTED label attached]
    → Discovery.SessionCompleted event

  GENERATION                                   AI produces a draft
    Worker consumes event, enqueues job
    → context assembled: graph traversal + retrieval (`40`)
    → AI service generates
    → ArtifactVersion (draft) + lineage        [Graph kernel]
    → AI.ArtifactGenerated event
    → cost recorded                            [GenerationRecord]

  REVIEW                                       human confers authority
    BA edits → new ArtifactVersion (draft)     [immutable; edits create versions]
    BA approves → Approval bound to version    [D-278]
    → Requirements.RequirementApproved event

  DERIVATION                                   the draft becomes usable
    → embedded into vector index               [approved only, D-446]
    → indexed for search
    → traceability projection updated
    → downstream contexts react:
         Design, Estimation, Planning, Quality

  USE                                          the graph pays off
    Design derives from it   → ArtifactLink(derives_from)
    Task implements it       → ArtifactLink(implements)
    Test verifies it         → ArtifactLink(verifies)
    Impact analysis traverses these links       [P-3]

  CHANGE                                       versioning proves its worth
    ChangeRequest raised
    → new version supersedes                   → ArtifactLink(supersedes)
    → impact analysis identifies affected downstream artifacts
    → prior version and its approval remain intact and referenced

  END OF LIFE
    Project closed → warm, then cold archive (`46`)
    Tenant deleted → removed from every store, verified (`48`)
```

**The critical property visible in this flow:** nothing is ever overwritten.
Every stage appends. This is what makes the record defensible in a dispute three
years later (P2), and it is the direct reason immutability is an aggregate-level
rule rather than a convention.

---

## Worked Flow · An Uploaded Document

Traced because it is the highest-risk ingestion path.

```
  Upload → Core API
    content-type verified by content, not extension
    size limit enforced
    malware scan
    → object storage, tenant-prefixed key, UNTRUSTED label
    → UploadedDocument row (metadata + content ref + provenance)

  Use in generation
    → worker fetches; label travels with it              [D-303]
    → AI service guardrail pipeline:
         delimited, labelled position — never concatenated
         into instruction text                            [SEC-9]
    → tool access minimized for this workflow
    → retrieval remains tenant-scoped regardless of
      anything the model is told                          [T-7]

  Output
    → schema-validated; non-conformant rejected cleanly   [D-95]
    → draft artifact requiring human approval             [D-36]

  Deletion
    → soft-deleted with the project (`46`)
    → hard-deleted after the recovery window
    → object storage key deleted
    → derived embeddings deleted
    → verified by the deletion audit                      [T-5]
```

**The document never becomes trusted.** It is untrusted at upload, in storage, in
the worker, and in the AI service — trust is not conferred by having crossed a
boundary (D-303).

---

## Worked Flow · Telemetry

Traced because it is the flow most likely to leak content accidentally.

```
  Emission (any container)
    structured record + tenantId + correlationId          [O-3]
    redacting logger strips sensitive fields              [D-79]
        │
  OTel Collector
    second redaction pass — last line of defence          [D-413]
    tail sampling; cardinality limiting                   [D-417, D-420]
        │
  Observability backend  (OUTSIDE THE PERIMETER)
    identifiers only, never tenant content                [D-304]
        │
  Retention: traces 14d · logs 30d hot · metrics 13m
        │
  Tenant deletion → tenant-scoped telemetry deletion
                    or documented retention expiry (`48`)
```

**Two redaction passes, deliberately.** Application-level redaction is primary
and precise; the collector pass catches what slipped through. This is the
defence-in-depth principle (D-75) applied to the flow most likely to carry
content out of the perimeter without anyone intending it.

---

## Flow Classification

Every flow is classified by what it carries, which determines its controls.

| Class | Contains | Controls |
| --- | --- | --- |
| **Tenant content** | Requirements, designs, uploads, generated artifacts | Tenant scoping, RLS, encryption, no cross-perimeter without contract |
| **Commercial** | Pricing, margin, contracts | Above, plus restricted audience (P1, P8) and field-level encryption |
| **Identity** | Users, credentials, sessions | Above, plus field encryption and PII localization (`48`) |
| **Operational** | Jobs, flags, system state | Standard controls; no tenant content |
| **Telemetry** | Identifiers, timings, counts | Redaction; no content, ever |
| **Derived** | Projections, indexes, embeddings | Inherits the class of its source — **including isolation** |

**The last row is the one that gets forgotten.** A projection built from tenant
content *is* tenant content, and is not exempt from RLS or from deletion
obligations because it is "just derived" (D-475).

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-480 | Trust classification and provenance assigned at ingestion, carried onward | Classification at point of use is duplicated and eventually forgotten |
| D-481 | Provenance cannot be backfilled, so it is captured at every ingestion path | Data ingested without it is permanently unexplainable |
| D-482 | Nothing leaves the persist transaction except through the outbox | Any other path is a dual write and a silent divergence |
| D-483 | All derivation is event-driven, never inline with the write | Otherwise a degradable dependency becomes a prerequisite for saving |
| D-484 | A projection bug is never data loss, because derived data is recomputable | What makes derived stores safe to iterate on |
| D-485 | AI grounding reads the primary and approved versions only | Stale or unapproved grounding compounds model error through the graph |
| D-486 | Every export path is enumerated and controlled as a disclosure | An unenumerated export is an unmanaged disclosure |
| D-487 | Tenant export must be genuinely complete, not technically compliant | It is an anti-lock-in trust commitment as much as a legal one |
| D-488 | Telemetry passes two independent redaction stages | The flow most likely to carry content out without anyone intending it |
| D-489 | Derived data inherits its source's classification, including isolation | "It's just a projection" is how tenant content escapes its controls |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| An ingestion path added without trust classification | Untrusted content treated as trusted | Classification is a required parameter of the ingestion API |
| Provenance omitted on a path, discovered later | Permanently unexplainable artifacts | Required field; asserted by test on every ingestion path |
| A derived store is treated as authoritative | Rebuild causes apparent data loss | Classification enforced (`36`); rebuild exercised on a schedule |
| Export path added without authorization or audit | Uncontrolled disclosure | New export paths require an ADR and appear in the perimeter table (`29`) |
| Telemetry redaction misses a new field | Content leaves the perimeter | Two-stage redaction; periodic log content audit |
| Projection excluded from tenant deletion | Compliance violation | Per-store deletion path with verification (`48`) |

## Dependencies

- **Depends on:** entity model (`42`), read/write models (`43`), events (`35`),
  security architecture (`39`), AI integration (`40`).
- **Depended on by:** retention and deletion (`46`), audit (`47`), GDPR (`48`).

## Future Improvements

- Generate the flow classification table from code annotations so it cannot
  drift from the paths that actually exist.
- Add an automated check that every ingestion path records provenance.
- Publish a per-data-class flow diagram as each context is implemented.
