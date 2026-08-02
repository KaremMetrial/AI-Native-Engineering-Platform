# Event Architecture

## Purpose

Define how events are produced, delivered and consumed. Events are the mechanism
that keeps bounded contexts decoupled (D-34) and the Delivery Graph coherent
across them. They are also the easiest part of a distributed design to get
subtly wrong in ways that surface as missing data months later.

## Scope

**In scope:** event taxonomy, envelope, the transactional outbox, delivery
semantics, ordering, consumer design, dead-letter handling, replay, and the
initial event catalogue.

**Out of scope:** event schema versioning (`27`), queue infrastructure (`37`).

---

## Event Taxonomy

**Decision.** Three distinct kinds of event, with different visibility, stability
obligations and consumers. Conflating them is the most common event-design error.

| Kind | Scope | Consumers | Stability | Contains |
| --- | --- | --- | --- | --- |
| **Domain event** | Within one context | That context only | Free to change | Rich internal detail |
| **Integration event** | Across contexts | Other contexts, Analytics | **Published contract** — additive-only (`27`) | Deliberately minimal |
| **Notification event** | Outward | Realtime gateway, external webhooks | Published contract | Identifiers and status only |

**Reasoning.** A domain event is an internal implementation detail: `Requirements`
raising `RequirementDraftRevised` to update its own read model. An integration
event is a public contract that other contexts build on and that is persisted
and replayed indefinitely. Treating them alike means either internal events are
frozen by external obligations, or public contracts churn with internal
refactoring.

**Alternatives.** *One event type for everything* — simplest, and every internal
refactor becomes a breaking change for other contexts. *Only integration events*
— loses the in-process decoupling that keeps a module's internals tidy.

**Trade-offs.** Two events are sometimes emitted for one occurrence — a rich
internal one and a minimal published one. This looks redundant and is the price
of being able to refactor internals without breaking consumers.

**Benefits.** Contexts refactor freely. The published surface stays small enough
to keep stable for a decade.

**Long-term impact.** Integration events are the most permanent contract in the
system (`23`) — persisted, replayed, and consumed by code not yet written.
Keeping them minimal and separate from internal events is what makes them
survivable.

---

## Event Envelope

Every integration event carries a fixed envelope. The payload varies; the
envelope never does.

| Field | Purpose |
| --- | --- |
| `eventId` | Unique identity — the consumer's deduplication key |
| `eventType` | `<Context>.<Entity><PastTenseVerb>` (`25`) |
| `eventVersion` | Schema version (D-275) |
| `occurredAt` | When the fact happened in the domain |
| `recordedAt` | When it was persisted — differs from `occurredAt` under retry |
| `tenantId` | **Required.** Consumers scope by it; missing means the event is rejected |
| `aggregateId` | The entity the fact concerns |
| `aggregateType` | Enables type-directed routing |
| `sequenceNumber` | Per-aggregate ordering |
| `correlationId` | Ties the whole logical operation together across hops |
| `causationId` | The event or command that directly caused this one |
| `actorId` | Who or what triggered it — null for system-originated |
| `payload` | The event-specific body |

**`correlationId` and `causationId` are distinct and both matter.** Correlation
answers "what user action started all of this?" and stays constant across the
entire causal tree. Causation answers "what immediately caused this?" and forms
a parent chain. With both, an event cascade can be reconstructed exactly —
without them, debugging an event-driven system means guessing from timestamps.

**`occurredAt` vs `recordedAt`** matters because a retried publish records later
than it occurred. Ordering and business logic use `occurredAt`; operational
analysis uses both, and a growing gap is a lag signal.

---

## The Transactional Outbox

**Decision.** Events are written to an outbox table in the same database
transaction as the state change that produced them. A separate relay publishes
from the outbox to the message bus.

**Reasoning.** This is the single most important decision in this document. The
naive alternative — commit the transaction, then publish — is a **dual write**,
and it is broken in both directions:

- Process crashes between commit and publish → the state changed and **nobody was
  told**. A requirement is approved, no plan is generated, and nothing anywhere
  indicates a failure. This is silent, permanent divergence.
- Publish succeeds, transaction rolls back → consumers act on something that
  **never happened**. Downstream artifacts are created for an approval that does
  not exist.

Neither failure is detectable without reconciliation, and both are the kind of
bug that surfaces weeks later as "the data is wrong and we don't know why."

The outbox makes the event and the state change **atomic**, because they are the
same transaction. Publication then becomes an at-least-once delivery problem —
which is solvable with idempotent consumers, unlike the dual-write problem, which
is not solvable at all.

```
   ┌──────────────── ONE TRANSACTION ────────────────┐
   │  UPDATE requirement SET status = 'approved'     │
   │  INSERT INTO outbox (event...)                  │
   └────────────────────┬────────────────────────────┘
                        │ commit
                        ▼
              ┌──────────────────┐
              │  Outbox Relay    │ polls unpublished rows,
              │  (leader-elected)│ publishes, marks published
              └────────┬─────────┘
                       ▼
                 Message bus ──▶ consumers (idempotent)
```

**Alternatives considered.**

| Alternative | Why not |
| --- | --- |
| **Dual write** (commit then publish) | Broken as described. The default, and wrong. |
| **Change data capture** from the WAL | Genuinely robust and avoids the relay; adds replication-slot infrastructure and emits *row changes* rather than *domain events*, so meaning must be reconstructed downstream. Revisit if outbox relay throughput becomes a constraint. |
| **Publish first, then commit** | Inverts the failure to phantom events — worse, since consumers act on fiction |
| **Event sourcing** | Solves it by construction, since the event *is* the state; rejected in `05` (D-38) as too much accidental complexity for this team |

**Trade-offs.** An extra table write per event, relay lag of typically under a
second, and a relay component that must not run in duplicate — hence
leader-elected, using the same mechanism as the Scheduler (D-312). Published rows
need pruning, or the outbox grows without bound.

**Benefits.** No lost events, no phantom events, ever. The property that makes
the whole event-driven design trustworthy rather than probabilistic.

**Long-term impact.** Silent event loss is corrosive precisely because it is
invisible: the graph slowly develops gaps, traceability quietly becomes
unreliable, and by the time anyone notices, the missing data is unrecoverable.
For a platform whose product *is* the traceable record (P2), this is
existential rather than merely annoying.

---

## Delivery Semantics

**Decision.** At-least-once delivery with idempotent consumers. Exactly-once is
not attempted.

**Reasoning.** Exactly-once delivery does not exist in a distributed system with
independent failures — what exists is at-least-once delivery plus idempotent
processing, which produces exactly-once *effects*. Systems that claim
exactly-once are either doing this internally or are wrong. Being explicit about
it means consumers are designed correctly rather than assuming a guarantee they
do not have.

**Consumer requirements** — every consumer must:

1. **Deduplicate on `eventId`**, against a processed-events record with TTL.
2. **Be idempotent in effect** even if deduplication fails — a defence in depth,
   since dedup records expire and replay can exceed the window.
3. **Tolerate out-of-order arrival** across aggregates.
4. **Tolerate unknown fields**, enabling producer evolution (D-275).
5. **Fail closed on missing tenant context** (D-57).

**At-most-once** is available for genuinely disposable events — presence updates,
typing indicators — where redelivery costs more than loss. Used sparingly and
never for anything that changes state.

---

## Ordering

**Decision.** Ordering is guaranteed **per aggregate**, not globally.

**Reasoning.** Global ordering requires a single serialization point, which is a
throughput ceiling and a single point of failure — precisely what a horizontally
scaled system exists to avoid. Per-aggregate ordering is achievable cheaply
(partition by `aggregateId`) and is what business logic actually needs:
`RequirementApproved` must not overtake `RequirementDrafted` for the *same*
requirement; whether it arrives before an unrelated requirement's event is
irrelevant.

**Mechanism.** Partition key is `aggregateId`; `sequenceNumber` is monotonic per
aggregate; a consumer detecting a gap waits briefly, then alerts rather than
silently skipping.

**Where cross-aggregate ordering seems necessary**, it is almost always a
modelling error — the two aggregates should be one, or the dependency should be
expressed as data rather than as arrival order. Reviewers treat a claimed
cross-aggregate ordering requirement as a signal to re-examine the boundary
(`32`).

---

## Consumer Architecture

```
   Message bus
        │
        ▼
   ┌─────────────────┐
   │ Consumer Group  │  one per (context, event-type) subscription
   └────────┬────────┘
            ▼
   ┌─────────────────┐
   │ Dedup Check     │  eventId seen? → ack and drop
   └────────┬────────┘
            ▼
   ┌─────────────────┐
   │ Context Restore │  tenant + trace from envelope; FAILS CLOSED
   └────────┬────────┘
            ▼
   ┌─────────────────┐
   │ Handler         │  business reaction
   └────┬───────┬────┘
        │ ok    │ error
        ▼       ▼
      ack   ┌──────────────┐
            │ Retry Manager│ bounded, backoff + jitter
            └──────┬───────┘
                   ▼ exhausted
            ┌──────────────┐
            │ Dead Letter  │ + alert, with full envelope preserved
            └──────────────┘
```

**Consumer groups are per subscription**, not per context, so one context's
subscription to `RequirementApproved` progresses independently of its
subscription to `EstimateCompleted`. A poison message in one subscription does
not stall the others.

**Dead letter handling:**

- The **full envelope is preserved**, so the event can be replayed after a fix.
  A DLQ entry lacking the original payload is a record that something failed with
  no way to recover it.
- Every DLQ entry raises an alert. A silently growing DLQ is silent data loss.
- Replay is an explicit, audited operation after remediation.
- **Poison messages are quarantined, not dropped** — the distinction between a
  bug we can fix later and data we destroyed.

---

## Initial Event Catalogue

The published integration events for Phase 1–2. Additions require the event to
be documented before it is emitted; the catalogue is a contract, not a log of
what happened to be built.

| Event | Producer | Primary consumers | Payload (minimal) |
| --- | --- | --- | --- |
| `Tenancy.TenantProvisioned` | Identity | Billing, Analytics, Platform Admin | tenantId, plan |
| `Tenancy.MembershipGranted` | Identity | Analytics, Notifications | tenantId, userId, role |
| `Tenancy.MembershipRevoked` | Identity | **All — cache invalidation**, Realtime | tenantId, userId |
| `Tenancy.StakeholderGrantIssued` | Identity | Audit, Notifications | grantId, resourceId, expiresAt |
| `Discovery.SessionCompleted` | Discovery | Requirements | sessionId, projectId |
| `Requirements.DocumentGenerated` | Requirements | Graph, Analytics, Notifications | artifactId, versionId, lineageRef |
| `Requirements.RequirementApproved` | Requirements | Design, Estimation, Planning, Quality, Graph | artifactId, versionId, approvedBy |
| `Requirements.ChangeRequested` | Requirements | Planning, Estimation, Commercial | artifactId, changeRequestId |
| `Design.ArchitectureApproved` | Design | Planning, Quality, Graph | artifactId, versionId |
| `Estimation.EstimateCompleted` | Estimation | Commercial, Planning | estimateId, range, confidence |
| `Commercial.ProposalAccepted` | Commercial | Planning, Contract process | proposalId, projectId |
| `Commercial.ContractExecuted` | Commercial | Planning, Billing, Operations | contractId, projectId |
| `Planning.TasksGenerated` | Planning | Execution, Quality, Analytics | planId, taskCount |
| `Execution.WorkCompleted` | Execution | Quality, Analytics, Estimation (calibration) | taskId, actualEffort |
| `Quality.VerificationRecorded` | Quality | Graph, Analytics | testCaseId, requirementId, result |
| `AI.ArtifactGenerated` | AI Orchestration | Graph, Billing (metering), Realtime | artifactId, versionId, model, cost |
| `AI.GenerationFailed` | AI Orchestration | Notifications, Analytics | jobId, reason |

**`Tenancy.MembershipRevoked` is the most security-sensitive event** in the
catalogue. Every context caching permission data must invalidate on it, and the
Realtime Gateway must drop affected subscriptions (D-328). A revocation that
propagates lazily means revoked access persists — which is why TTL-only caching
of permissions is prohibited (D-71).

**`Execution.WorkCompleted` carrying `actualEffort` is what closes the G2 loop.**
It is the mechanism by which the graph becomes a learning system rather than a
record, and it is why Estimation subscribes to an Execution event.

---

## Replay

**Decision.** Integration events are retained and replayable for read-model
rebuilds and new consumers.

**Reasoning.** A new consumer added in year three needs the history to build its
projection. A corrupted read model must be rebuildable without touching the
system of record. Both require durable, ordered, replayable events — and both are
impossible if events are discarded after delivery.

**Constraints:**

- Replay targets a **specific consumer**, never a broadcast. A global replay
  would re-trigger notifications, re-charge AI spend and re-fire webhooks.
- **Side-effecting consumers are not replayable.** Notification and webhook
  consumers are explicitly excluded — this is why keeping outward-facing
  consumers separate from projection-building consumers matters.
- Replay is audited, rate-limited and runs against a rebuild target rather than
  the live projection where possible.

**Retention:** integration events 24 months, domain events 90 days. The
asymmetry reflects their different purposes — integration events are a contract
and a rebuild source; domain events are internal plumbing.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-367 | Three event kinds with different stability obligations | Conflating them freezes internals or churns public contracts |
| D-368 | Integration events are deliberately minimal | The most permanent contract in the system; consumed by code not yet written |
| D-369 | Fixed envelope with `correlationId` and `causationId` both | Correlation gives the logical operation; causation gives the parent chain — debugging needs both |
| D-370 | `occurredAt` and `recordedAt` are separate fields | They diverge under retry, and the gap is a lag signal |
| D-371 | **Transactional outbox; dual writes prohibited** | Dual writes lose events or emit phantoms, both silently and undetectably |
| D-372 | Outbox relay is leader-elected and prunes published rows | Duplicate relays double-publish; unpruned outbox grows without bound |
| D-373 | At-least-once delivery with idempotent consumers; exactly-once not attempted | Exactly-once delivery does not exist; exactly-once *effects* do |
| D-374 | Consumers deduplicate on `eventId` **and** are idempotent in effect | Dedup records expire; replay can exceed the window |
| D-375 | Ordering guaranteed per aggregate, not globally | Global ordering needs a serialization point — a throughput ceiling and SPOF |
| D-376 | A claimed cross-aggregate ordering requirement signals a modelling error | Usually the boundary is wrong, or the dependency should be data not arrival order |
| D-377 | Consumer groups per subscription, not per context | A poison message must not stall unrelated subscriptions |
| D-378 | DLQ preserves the full envelope and alerts on every entry | A DLQ entry without its payload is unrecoverable; a silent DLQ is silent data loss |
| D-379 | Events are documented in the catalogue before they are emitted | The catalogue is a contract, not a log of what got built |
| D-380 | Replay targets one consumer; side-effecting consumers are non-replayable | A broadcast replay re-notifies, re-charges and re-fires webhooks |
| D-381 | Integration events retained 24 months; domain events 90 days | Different purposes: contract and rebuild source versus internal plumbing |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Outbox relay lag grows under load | Delayed cross-context reaction; user-visible staleness | Lag monitored and alerted; relay scales with leader failover; CDC as the escalation path |
| A developer bypasses the outbox and publishes directly | Silent event loss returns | Publishing API available only through the Outbox Writer; direct bus access restricted by import rule |
| Consumer idempotency implemented incorrectly | Duplicate side effects | Idempotency exercised by tests that deliberately redeliver |
| DLQ grows unnoticed | Silent data loss | Alert on every entry, not on a threshold |
| Event catalogue drifts from what is emitted | Consumers built against fiction | Contract tests assert emitted events match the catalogue |
| Replay re-triggers side effects | Duplicate customer-visible actions | Side-effecting consumers explicitly excluded and marked in the catalogue |
| Integration events become large as producers add convenience fields | Coupling to producer internals returns | Minimality reviewed; consumers needing more should query, not expect it in the event |

## Dependencies

- **Depends on:** domain model (`32`), context map (`33`), communication (`34`),
  versioning (`27`).
- **Depended on by:** data architecture (`36`), observability (`38`), resilience
  (`41`).

## Future Improvements

- Publish machine-readable schemas for every catalogued event, generated into
  `packages/contracts` alongside the API contract.
- Add outbox relay lag to the SLO set once baseline latency is measured.
- Evaluate change data capture if relay throughput becomes a constraint — the
  migration path is deliberately preserved.
