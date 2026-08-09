# Graph

The Delivery Graph kernel (`docs/product/03-core-modules-and-scope.md`) —
`Artifact`/`ArtifactVersion`/`ArtifactLink`/`Approval`
(`docs/architecture/design/32-domain-model-and-ddd.md`) and a minimal
`Project` entity for artifacts to attach to. This is the platform's
differentiating feature: "what depends on this?" reverse impact analysis
across requirements, tasks, tests, and their approvals.

## Scope

**In:** creating a project; creating an artifact with its first version;
appending new versions (no update path — D-336); linking two versions with
a closed vocabulary of link types (D-338); recording an approval decision
against a specific version (D-278); depth-bounded (≤5, P-3) reverse impact
traversal; reading back everything above (list projects, get a project, list
a project's artifacts, get an artifact with its version history, list a
version's links and approvals) — the minimum read surface a client needs to
use the writes, not the `Project` workspace deliverable below.

**Out, deliberately** — later phases: artifact templates, diffing between
versions, bulk import, the full `Project` workspace (membership, settings),
anything beyond the six link types in `LinkType` (adding one requires an
ADR per D-338).

## Reads: direct repository queries, not a read model

Every read above is served by a direct query against the same normalized
tables the writes use — no projection. Per
`docs/architecture/data/43-write-and-read-models.md`, a read model is
justified only when at least two of five criteria hold (cross-context join,
divergent shape, expensive query, read-heavy skew, acceptable staleness);
none apply here — these are single-context "fetch this aggregate" and "list
these entities filtered by tenant" reads, exactly what the write model
serves best with strong consistency. `ProjectRepository::findAll()` and
`ArtifactRepository::findAllForProject()` use `DB::table()` rather than
Eloquent, for the same PHPStan-generics reason documented on
`EloquentArtifactRepository::findById()`. `findAllForProject()` batches its
version fetch in one query rather than one per artifact, to avoid N+1.

## Layout

Standard module layering (`docs/delivery/11-repository-and-folder-strategy.md`):

```
Domain/           Project, Artifact (+ ArtifactVersion child entity),
                  ArtifactLink, Approval -- four separate aggregates
                  (D-335: the graph as a whole is not one aggregate, or
                  every write anywhere would serialize against every other
                  write). Lineage value object carries AI generation
                  provenance; Lineage::human() is the human-authored path
                  (P-9: no AI inference in a synchronous request path).
                  Framework-free (D-130).
Application/      CreateProject, CreateArtifact, CreateArtifactVersion,
                  LinkArtifactVersions, ApproveArtifactVersion,
                  TraverseImpact use cases.
Infrastructure/   Eloquent models and repository implementations,
                  PostgresImpactTraversal (a recursive CTE, not an ORM
                  query -- see its own docblock), the service provider
                  binding Domain interfaces to these implementations.
Presentation/     Controllers and FormRequests. Authentication and tenant
                  binding are shared middleware, not owned here -- see
                  `app/Tenancy/Presentation/BindTenantContext.php`.
```

`BindTenantContext` and `ReadsValidatedStrings` live in `app/Tenancy` and
`app/Shared` respectively, not in `app/Identity` or `app/Graph`: per
`docs/delivery/11-repository-and-folder-strategy.md`, "only Tenancy and
Shared are importable by all" — two regular bounded contexts (Identity,
Graph) reaching into each other's Presentation layers would itself be a
boundary violation, so anything both need was relocated before Graph
reused it.

## Aggregate boundaries (D-333)

One aggregate per transaction. `Artifact` owns its `ArtifactVersion`
children (creating a version is a write to `Artifact`, not a separate
aggregate). `ArtifactLink` and `Approval` are independent aggregates that
reference version ids — validated on write via `ArtifactVersionFinder`, a
read-side lookup that doesn't require loading the parent `Artifact`.

## The `artifact_links` and `artifact_versions` tables

RLS-enforced tenant-scoped tables, following the same pattern the Identity
module established for `memberships`
(`tests/Isolation/GraphIsolationTest.php` replaces the Phase 0
`graph_poc_*` spike tables, deleted in the migration that creates
`artifact_versions`). `artifact_links` carries the composite indexes
(`artifact_links_forward_idx`, `artifact_links_reverse_idx`) that
`docs/governance/22-graph-traversal-benchmark-results.md` measured —
`PostgresImpactTraversal`'s recursive CTE is the real query that benchmark
was proving Postgres could serve, this time deduplicating paths with
`SELECT DISTINCT`.

`artifacts.current_version_id` and `artifact_versions.artifact_id` are
mutually referencing foreign keys by design (the migration creates
`artifacts` without the FK, then adds it once `artifact_versions` exists).
`EloquentArtifactRepository::save()` writes in three steps inside one
transaction — artifact row without `current_version_id`, then version
rows, then the `current_version_id` update — to satisfy that ordering.
