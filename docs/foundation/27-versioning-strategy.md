# Versioning Strategy

## Purpose

Define how every versioned thing in the platform evolves without breaking its
consumers. Versioning discipline is what allows a ten-year-old system to keep
changing; its absence is the mechanism by which old systems freeze — every
change becomes risky, so changes stop, so the system ossifies.

## Scope

**In scope:** what is versioned and why, public API versioning, deprecation
policy, database and event schema evolution, internal package versioning,
AI prompt and model versioning, artifact versioning in the Delivery Graph, and
documentation versioning.

**Out of scope:** release process mechanics (`15`) and branching (`12`).

---

## What Must Be Versioned, and Why

**Decision.** Anything with a consumer that we do not control and cannot update
atomically requires an explicit versioning contract.

**Reasoning.** The need for versioning is determined by one question: *can we
change this and all its consumers in a single atomic change?* If yes, versioning
is overhead — the monorepo (D-102) makes exactly this possible for most internal
code, and that is one of its principal benefits. If no, an unversioned change is
a breakage waiting for a deployment window.

| Artifact | Consumer | Atomic change possible? | Versioned |
| --- | --- | --- | --- |
| Public REST API | External clients, integrations | No | **Yes — explicitly** |
| Frontend↔API contract | Our SPA | Nearly — but not during rollout | **Yes — compatibility window** |
| Database schema | Two app versions during rolling deploy | No | **Yes — expand-contract** |
| Domain events | Consumers, replays, stored history | No | **Yes — additive-only** |
| Delivery Graph artifacts | The product's own domain model | N/A — it is a feature | **Yes — immutable versions** |
| Prompts | Generation lineage, evaluation baselines | Yes, but lineage must be reproducible | **Yes — content-addressed** |
| Model selection | Evaluation baselines, output stability | No — the provider changes it | **Yes — pinned** |
| Internal module interfaces | Other modules in the same repo | Yes | No — refactor freely |
| Shared internal packages | Apps in the same repo | Yes | No — versioned by commit |
| This documentation set | Engineers, agents | Yes | Lightweight |

**The middle rows are the ones teams miss.** Database schema and event schema
both have non-obvious consumers — the previous application version still running
during a rolling deploy, and historical events already persisted. Both are
covered in detail below.

**Trade-offs of versioning selectively.** A uniform "version everything" policy
is simpler to state and produces ceremony around internal interfaces that could
simply be refactored. We accept the judgment cost of the table above in exchange
for not paying versioning overhead on the ~80% of interfaces that are internal.

---

## Public API Versioning

**Decision.** Major versions in the URI path (`/api/v1/…`). Within a major
version, only additive, backward-compatible change is permitted.

**Reasoning.** URI versioning is visible, unambiguous, trivially routable,
cacheable, and immediately obvious in logs, traces and support conversations.
When a customer reports a problem, the version is in the request line rather
than in a header nobody captured.

**Alternatives considered.**

| Alternative | Strengths | Why not chosen |
| --- | --- | --- |
| **Header versioning** (`Accept: application/vnd.x.v2+json`) | Purist REST; the URI identifies the resource, not its representation | Invisible in logs and browser tools; easy for clients to omit and get an unintended default; harder to route at the edge; harder to explain to integrators |
| **Query parameter** (`?version=2`) | Simple | Pollutes caching; easily dropped by intermediaries; ambiguous when absent |
| **No versioning; never break** | Simplest possible contract | Unrealistic over ten years — some breaking change eventually becomes necessary, and without a mechanism it will be made anyway, badly |
| **Version per endpoint** | Granular; only affected endpoints churn | Combinatorial complexity for clients; nobody can state which version "the API" is |

**Trade-offs.** URI versioning is technically impure — the same resource has two
identifiers. We accept that in exchange for operational clarity, which matters
more over a decade than REST orthodoxy.

Running two major versions simultaneously also costs real maintenance: two code
paths, two test suites, two sets of behaviour to reason about. This is why major
versions are rare by design.

**Benefits.** The version is visible everywhere it is needed — logs, traces,
support tickets, integrator documentation — without special tooling. Routing and
edge caching work without inspecting headers. And integrators can adopt a new
version incrementally, endpoint by endpoint, rather than in one coordinated
migration.

**What is additive (permitted within a major):** new endpoints; new optional
request fields; new response fields; new optional query parameters; new enum
values *where the contract documented that clients must tolerate unknown values*;
relaxing a validation constraint.

**What is breaking (requires a new major):** removing or renaming any field;
changing a field's type or semantics; making an optional field required;
tightening validation; removing an enum value; changing an error code; changing
default behaviour; changing pagination or sort semantics.

**Two subtleties worth stating**, because they are the ones that break clients
without anyone realizing a change was breaking:

- **Changing a field's *meaning* while keeping its name and type is breaking**,
  and is worse than removing it, because nothing fails loudly — the client
  silently misinterprets data.
- **Adding an enum value is breaking unless clients were told to expect it.**
  A client with an exhaustive switch will fail on an unknown value. The contract
  must state the expectation up front; it cannot be added retroactively.

**Long-term impact.** Additive-only discipline within a major version means most
evolution never requires a version bump at all. Teams without this discipline
reach v4 in three years, maintain four code paths, and spend their capacity on
migration rather than product.

---

## Deprecation Policy

**Decision.** Nothing is removed without an announced deprecation period,
telemetry showing usage has ceased, and a stated sunset date.

**Reasoning.** Versioning without deprecation is incomplete: it lets you add a
new version but never retire the old one, so versions accumulate forever and the
maintenance cost grows monotonically. The deprecation process is what makes
versioning *sustainable* rather than merely possible.

**Process:**

1. **Announce.** Changelog, documentation marked deprecated, and — where the
   consumer is identifiable — direct notice. State the replacement and the sunset
   date at announcement, not later.
2. **Signal in-band.** `Deprecation` and `Sunset` response headers on affected
   endpoints, so a client discovers it programmatically rather than by reading a
   changelog nobody reads.
3. **Instrument.** Per-consumer usage telemetry on every deprecated element. This
   is the step most often skipped, and without it step 5 is guesswork.
4. **Support the window.** Minimum 6 months for a public API major version; 90
   days for a deprecated field within a major; longer for enterprise contracts
   where committed.
5. **Verify zero usage** by telemetry, not by asking. Contact remaining consumers
   directly.
6. **Remove**, and delete the code, tests, documentation and telemetry (P13).

**Alternatives.** *Remove with notice but no telemetry* — the usual practice, and
the reason removals break customers who never saw the notice. *Never remove* —
accumulating maintenance burden and a growing surface of behaviour nobody
understands. *Remove aggressively* — destroys trust in a B2B product where
integrations are built by third parties.

**Trade-offs.** Long windows mean carrying old code longer than is comfortable,
including through refactors that would be simpler without it.

**Benefits.** Removals actually complete, so versions do not accumulate
indefinitely. Integrators experience the platform as dependable, which is what
justifies building deeply against it. And in-band signalling means a client
discovers its own obsolescence programmatically rather than through an outage.

**Long-term impact.** Deprecation discipline is what distinguishes an API that is
still evolving in year ten from one that is frozen because nobody dares change
it. It is also a trust asset: integrators who have been broken once become
reluctant to build deeply, and depth of integration is what makes a platform
sticky.

---

## Database Schema Versioning

**Decision.** Expand-contract migrations, forward-only (D-120, D-153). Restated
here as the versioning contract it is.

**The versioning insight:** during a rolling deploy the schema has *two*
consumers — application version N and version N+1 — running simultaneously. The
schema is therefore a versioned interface between them, and every migration must
be compatible with both.

| Phase | Change | Both versions work? |
| --- | --- | --- |
| Expand | Add nullable column / new table | Yes — old ignores it |
| Migrate | Backfill in batches; new code writes both | Yes |
| Switch | New code reads new structure | Yes |
| Contract | Remove old structure | Yes — nothing reads it |

**Never in one deploy:** rename a column, change a type narrowingly, add a NOT
NULL column without a default, or drop anything still referenced. Each of these
breaks version N while version N+1 is still rolling out — a partial outage that
looks like intermittent errors and is diagnosed slowly.

**Forward-only** because a reverse migration against data written by the new
version usually loses data. The application is the thing that rolls back; the
schema stays ahead. This only works *because* every step is backward compatible —
the two decisions are inseparable.

**Long-term impact.** Schema is the longest-lived artifact in the system (`23`).
Migration discipline determines whether the schema can keep evolving for a decade
or becomes a museum of columns nobody dares touch.

---

## Domain Event Versioning

**Decision.** Event schemas evolve additively only. Every event carries a version
in its envelope. Consumers ignore unknown fields; producers never remove or
repurpose one.

**Reasoning.** Events are the most under-appreciated versioning problem in an
event-driven system, for a reason that only becomes apparent later: **events are
persisted**. An event emitted today may be consumed by a service written in two
years, or replayed to rebuild a read model in five. The producer that wrote it no
longer exists in the form that wrote it. There is no way to migrate a consumer
that has not been written yet.

**Rules:**

| Rule | Reason |
| --- | --- |
| Add fields freely, always optional | Old consumers ignore them |
| Never remove a field | A replay would break every consumer that reads it |
| **Never repurpose a field name** | The most dangerous change of all — old persisted events carry the old meaning, and nothing detects the mismatch |
| Never change a field's type | Deserialization breaks on historical events |
| Version in the envelope, not the type name | `RequirementApproved` stays one event type through its evolution |
| Consumers tolerate unknown fields | Enables producer evolution without coordinated deploys |
| A genuinely incompatible change is a **new event type** | Cleaner than a v2 of an existing type; both can coexist during migration |

**Field repurposing deserves the emphasis.** Renaming `owner` to mean something
different, or changing `status` values' semantics, means historical events say
one thing and are interpreted as another. Nothing fails; the data is simply
wrong, and it is wrong retroactively across the entire event history. This is the
one event-versioning mistake that is effectively unrecoverable.

**Alternatives.** *Version the event type name* (`RequirementApprovedV2`) —
explicit, and fragments subscriptions so every consumer must handle every
variant forever. *Migrate stored events on schema change* — rewriting history,
which destroys the audit value that made events worth persisting. *Upcasting on
read* — transform old events to the current shape at consumption; genuinely
viable and adds a transformation layer that must itself be maintained and
tested for every historical version. Worth revisiting if additive-only becomes
unwieldy, and unnecessary until then.

**Trade-offs.** Additive-only accumulates deprecated fields in event payloads
over time. Accepted — payload bloat is a modest cost; a broken replay is not.

**Benefits.** Producers evolve without coordinating with consumers, which is the
decoupling that made events worth adopting (D-34). Replay works indefinitely,
so read models can be rebuilt years later — the property that makes event-driven
analytics viable.

**Long-term impact.** Events are the closest thing in the system to a permanent
record. Discipline here determines whether the event history remains an asset or
becomes a liability nobody trusts to replay.

---

## Delivery Graph Artifact Versioning

Distinct from all of the above: this is **product functionality**, not
engineering hygiene. Recorded here because the principles are the same and the
consistency matters.

- **Artifacts are immutable once created.** An edit produces a new version, never
  a mutation. This is what makes lineage defensible (P2).
- **Versions are linear per artifact**, with explicit supersession links.
- **Approval is bound to a specific version**, never to the artifact generally.
  An approval that floats to the latest version is worthless — it would mean a
  contract was approved against text nobody read.
- **Downstream links reference a version**, so impact analysis can distinguish
  "derived from the approved version" from "derived from a superseded draft."
- **Nothing is deleted**; artifacts are archived, preserving the historical
  record.

**Long-term impact.** This is the product's core asset. An immutable, versioned,
linked history is what a customer can defend in a dispute three years later —
and it is the reason the platform is a system of record rather than a document
generator.

---

## AI Prompt and Model Versioning

**Decision.** Prompts are content-addressed and semantically versioned. Model
identifiers are pinned explicitly and never resolved to a moving alias.

**Reasoning — prompts.** Every generation records the exact prompt version that
produced it (D-93). Without that, an output cannot be reproduced, a quality
regression cannot be attributed, and evaluation baselines are meaningless. The
prompt is part of the lineage, and lineage is what makes AI output defensible
(U-4).

**Reasoning — models, and this is the more important half.** Model aliases that
track "latest" silently change behaviour underneath a system. The consequences
are specific and severe:

- **Evaluation baselines become invalid** without any signal. A golden-set score
  computed last month described a different model.
- **Quality regressions cannot be attributed** — output changed, nothing in our
  repository changed.
- **Reproducibility is lost**, so a customer disputing a generated artifact
  cannot be given the conditions that produced it.
- **Cost changes unannounced**, breaking unit economics assumptions ($-2).

**Therefore: pin explicit model identifiers.** Model upgrades are deliberate
changes — pinned version bumped in configuration, evaluation suite run,
regression thresholds checked (D-96), then released. A model change is treated
exactly like a code change, because behaviourally it is one.

**Alternatives.** *Track latest automatically* — always current, and surrenders
control of behaviour, cost and evaluability to a third party's release schedule.
*Pin forever* — eventually runs a deprecated model; the provider retires it and
forces an unplanned migration. **Neither: pin explicitly, upgrade deliberately,
on our schedule within the provider's support window.**

**Trade-offs.** Pinning means we are not automatically on the best available
model, and capturing an improvement requires deliberate work. Accepted — that
work is an evaluation run, which we need anyway to know whether it *is* an
improvement for our workloads.

**Long-term impact.** The AI layer is the fastest-churning part of the system
(`23`). Version discipline is what keeps that churn controlled rather than
ambient, and what makes the evaluation harness meaningful over time.

---

## Internal Package and Documentation Versioning

**Internal packages** (`packages/*`) are **not** semantically versioned. They are
versioned by commit, alongside every consumer, in one repository.

**Reasoning.** Semver exists to communicate compatibility to consumers you cannot
update. Inside a monorepo, every consumer is updated in the same commit (D-102).
Applying semver here would be ceremony that communicates nothing to nobody — and
worse, it creates a false impression that packages are independently consumable.

**This changes the moment a package is published externally.** At that point it
acquires consumers we cannot update atomically, and full semver plus a
deprecation policy applies. The trigger is publication, not size or importance.

**Documentation** — this foundation set — carries a lightweight version: the
charter has an explicit version and revision history; foundation documents are
versioned by commit and reviewed at phase boundaries; ADRs are immutable and
superseded rather than edited (D-182). No formal scheme, because the consumer is
a human or agent reading the current state, not a system depending on a contract.

---

## Decisions

| ID | Decision | Rationale |
| --- | --- | --- |
| D-267 | Versioning required only where consumers cannot be updated atomically | Avoids ceremony on the ~80% of interfaces that are internal to the monorepo |
| D-268 | Public API uses major versions in the URI path | Visible in logs and traces; unambiguous; trivially routable |
| D-269 | Within a major version, only additive change is permitted | Most evolution then requires no version bump at all |
| D-270 | Changing a field's meaning is breaking, and worse than removing it | Nothing fails loudly; the client silently misinterprets data |
| D-271 | Adding an enum value is breaking unless tolerance was documented up front | Exhaustive client switches fail; the expectation cannot be added retroactively |
| D-272 | Deprecation requires announcement, in-band headers, telemetry, and verified zero usage | Removal without usage telemetry breaks customers who never saw the notice |
| D-273 | Minimum 6-month window for API majors, 90 days for fields | Third-party integrators need time; broken integrators stop integrating deeply |
| D-274 | Database schema is a versioned interface between two running app versions | This is why expand-contract is mandatory rather than recommended |
| D-275 | Event schemas evolve additively; fields are never removed or repurposed | Events are persisted and replayed; a consumer may not be written yet |
| D-276 | Repurposing an event field is prohibited absolutely | Retroactively corrupts the entire event history with no failure signal |
| D-277 | An incompatible event change becomes a new event type | Cleaner than versioning a type; both coexist during migration |
| D-278 | Graph artifacts are immutable; approval binds to a specific version | A floating approval means a contract was approved against unread text |
| D-279 | Model identifiers are pinned explicitly; aliases tracking "latest" are prohibited | Silent behaviour change invalidates evaluations and destroys reproducibility |
| D-280 | Model upgrades are deliberate changes gated by the evaluation suite | A model change is behaviourally a code change |
| D-281 | Internal packages are versioned by commit, not semver | Semver communicates to consumers you cannot update; there are none |
| D-282 | Publication externally is the trigger for full semver and deprecation policy | Not size, not importance — the existence of unreachable consumers |

## Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| A breaking change ships as additive by mistake | Silent client breakage | Contract tests verify conformance (`14`); breaking-change checklist in review |
| Deprecation announced but never completed | Permanent dual maintenance — the worst outcome | Sunset date and owner mandatory at announcement; stalled deprecations surfaced in review |
| Usage telemetry missing on a deprecated element | Removal breaks an unknown consumer | Telemetry is step 3 of the process, before the window starts |
| Event field repurposed under delivery pressure | Retroactive corruption of event history | Prohibited absolutely; called out in the review checklist for event changes |
| Model pin left stale until the provider retires it | Forced unplanned migration | Provider deprecation notices tracked; upgrades planned within the support window |
| Two API majors maintained indefinitely | Maintenance burden grows monotonically | Majors are rare by design; deprecation begins when the successor ships |
| Semver applied internally as cargo cult | Ceremony and a false impression of independent consumability | D-281 explicit; publication is the only trigger |

## Dependencies

- **Depends on:** architecture (`05`), development strategy (`12`, expand-contract),
  AI strategy (`10`), naming standards (`25`).
- **Depended on by:** future evolution (`28`), review process (`16`), testing
  strategy (`14`, contract tests).

## Future Improvements

- Add automated breaking-change detection against the published OpenAPI contract
  in CI, so the judgment in this document becomes a gate.
- Add automated deprecation-usage reporting so step 5 is a dashboard rather than
  an investigation.
- Define the event catalogue with explicit schema evolution history before the
  first cross-context event ships.
