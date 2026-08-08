# Discovery

The Discovery context (`docs/product/03-core-modules-and-scope.md`, C2) —
idea intake and structured elicitation: `DiscoverySession`, `Question`,
`Response`, `Assumption`, `Constraint`
(`docs/architecture/data/42-entity-model-and-ownership.md`). Kept separate
from Requirements (C3) deliberately: Discovery's language is exploratory and
its outputs are *unvalidated* — collapsing the two is how unverified
assumptions get laundered into signed commitments.

## Scope

**In:** starting a discovery session against a project; structured
elicitation (asking questions, recording responses — more than one response
per question is allowed, since more than one stakeholder may answer);
capturing assumptions and constraints; completing a session.

**Out, deliberately** — not because they're hard, but because nothing needs
them yet:

- **`StakeholderInput` and `UploadedDocument`.** The entity catalogue lists
  both, but `UploadedDocument` is "reference to blob, untrusted-labelled" —
  blob storage plus untrusted-content handling (`docs/architecture/data/44-data-flow-architecture.md`:
  "uploaded brief → object storage `[UNTRUSTED label attached]`") is a
  separate, non-trivial infrastructure concern that deserves its own scoped
  pass, not a bolt-on to session/question/response CRUD.
- **Publishing `Discovery.SessionCompleted`**
  (`docs/architecture/design/35-event-architecture.md`). That event's
  delivery mechanism is a transactional outbox with a leader-elected relay —
  genuinely the single most load-bearing decision in that document, and
  cross-cutting shared infrastructure, not something Discovery owns.
  Building it now would mean standing up a relay for a topic with zero
  subscribers: Requirements (C3), the only consumer, doesn't exist yet
  either. `CompleteDiscoverySession` marks the session complete and stops
  there — see its own docblock. The outbox becomes load-bearing, not
  speculative, the moment Requirements needs to react to it.
- **AI-assisted synthesis** ("Discovery → BRD generation → approval",
  `docs/delivery/14-testing-strategy.md`). That's Requirements plus AI
  Orchestration, neither of which exists yet.

## Layout

Standard module layering (`docs/delivery/11-repository-and-folder-strategy.md`):

```
Domain/           DiscoverySession (aggregate root) plus four separate
                  aggregates -- Question, Response, Assumption, Constraint --
                  that reference a session by id rather than living as
                  children of it. Same reasoning Graph applies to
                  ArtifactLink and Approval (D-335): concurrent
                  stakeholders answering different questions in the same
                  session must not serialize against each other or against
                  the session itself. Framework-free (D-130).
Application/      StartDiscoverySession, AddQuestion, RecordResponse,
                  CaptureAssumption, CaptureConstraint,
                  CompleteDiscoverySession use cases.
Infrastructure/   Eloquent models and repository implementations, the
                  service provider binding Domain interfaces to them.
Presentation/     Controllers and FormRequests, under the same
                  auth:sanctum + BindTenantContext middleware Graph uses
                  (app/Tenancy/Presentation/BindTenantContext.php).
```

## Depending on Graph

`StartDiscoverySession` validates that a project exists before starting a
session against it, via `App\Graph\Application\FindProject` — a small
read-side query added to Graph specifically for this
(`docs/delivery/11-repository-and-folder-strategy.md`: "Graph is importable
by all" and "a module imports another module only via its Application
layer"). Discovery does not reach into Graph's Domain or Infrastructure
directly.

`discovery_sessions.project_id` is a real foreign key, not a plain
validated-on-write identifier — `Project` is shared-kernel
(`docs/architecture/data/42-entity-model-and-ownership.md`: "to the shared
kernel — foreign key, the kernel is stable and universally depended upon",
D-463), not a cross-context reference under D-461.
