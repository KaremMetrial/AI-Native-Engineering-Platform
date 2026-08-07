# Features

One directory per bounded context, mirroring the backend (`apps/api/app/Modules`)
per `docs/delivery/11-repository-and-folder-strategy.md`. Each feature owns its
components, hooks, API calls and types.

**Does not own:** cross-feature UI primitives (`../shared`), routing and layout
(`../app`), or framework-adjacent integrations (`../lib`). Features do not
import from each other — shared code moves to `shared/` instead.

Empty until the first feature (Requirements, per the Phase 1 roadmap) lands.
