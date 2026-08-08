# Requirements

The Requirements context (`docs/product/03-core-modules-and-scope.md`, C3) —
BRD, SRS, functional and non-functional requirements, acceptance criteria:
`RequirementDocument`, `Requirement`
(`docs/architecture/data/42-entity-model-and-ownership.md`). "The most
valuable context in the system: the input to nearly every downstream
generation task and the anchor of every trace path." Consumes Discovery's
output (Customer–Supplier per `docs/architecture/design/33-context-map-and-service-boundaries.md`)
but is not built on it — this module's create/approve flow is independent
of any particular Discovery session.

## Scope

**In:** creating a requirement document (BRD or SRS) against a project;
adding requirements with acceptance criteria to a draft document; approving
an individual requirement; approving a document once every requirement
under it is approved (`docs/architecture/design/32-domain-model-and-ddd.md`:
"cannot be approved while any requirement is incomplete").

**Out, deliberately** — not because they're hard, but because building them
now would mean guessing at a contract that isn't specified yet:

- **AI-assisted generation.** "Discovery → BRD generation → approval"
  (`docs/delivery/14-testing-strategy.md`) needs the AI orchestration stack
  (queued generation per P-9, the multi-provider gateway, prompt engine) —
  none of which exists yet. This module's create/approve flow is the
  human-authored path only, the same posture Graph's `Lineage::human()`
  and Discovery's structured capture already take.
- **Publishing to the Delivery Graph.** The entity catalogue shows
  `Requirements.DocumentGenerated` and `Requirements.RequirementApproved`
  carrying an `artifactId`/`versionId` into Graph, and
  `docs/architecture/data/44-data-flow-architecture.md`'s GENERATION/REVIEW
  flow implies every edit to a document produces a new Graph
  `ArtifactVersion`. That versioning contract is specified for the
  AI-generation path specifically ("AI service generates → ArtifactVersion
  (draft)... BA edits → new ArtifactVersion (draft)"); it is not obvious
  what the equivalent contract should be for a document that was never
  AI-generated, and guessing wrong here would mean ripping out real
  integration code later. `RequirementDocument` does not yet have an
  `artifact_id`. This is the one deferred piece most likely to need a
  correction pass once the AI-generation flow exists to define it properly.
- **`ChangeRequest`, `RequirementRelation`, `TraceabilityEntry`.** Formal
  change control and within-document requirement relations are a layered
  feature on top of documents/requirements existing at all, not core to
  getting the first real ones created. `TraceabilityEntry` is very likely a
  read-model projection over Delivery Graph links (D-464: "semantic
  traceability is Delivery Graph links," never a separate storage
  mechanism) rather than its own write path — that's an optimization,
  deferred along with other read-side work in this codebase so far.

## Layout

Standard module layering (`docs/delivery/11-repository-and-folder-strategy.md`):

```
Domain/           RequirementDocument (aggregate root, Draft/Approved) and
                  Requirement (separate aggregate -- individual
                  requirements are edited, approved and traced
                  independently, so a document-level aggregate would
                  serialize all editing within a document). AcceptanceCriterion
                  is a value object on Requirement, same reasoning as
                  Lineage on ArtifactVersion. RequirementAlreadyApproved,
                  RequirementDocumentAlreadyApproved and
                  IncompleteRequirementsExist are named for the rule they
                  enforce (docs/foundation/25-naming-standards.md's own
                  example is literally RequirementAlreadyApproved).
                  Framework-free (D-130).
Application/      CreateRequirementDocument (validates project via Graph's
                  FindProject), AddRequirement (blocked once the document
                  is approved), ApproveRequirement,
                  ApproveRequirementDocument (the cross-aggregate "all
                  requirements approved" check -- see
                  RequirementDocument's docblock for why it cannot live in
                  the Domain layer).
Infrastructure/   Eloquent models and repository implementations, the
                  service provider binding Domain interfaces to them.
Presentation/     Controllers and FormRequests, under the same
                  auth:sanctum + BindTenantContext middleware every other
                  module uses.
```

`requirement_documents.project_id` is a real foreign key -- `Project` is
shared-kernel (D-463), the same reasoning Discovery's `discovery_sessions`
table uses.
