# Features

One directory per bounded context, mirroring the backend (`apps/api/app/Modules`)
per `docs/delivery/11-repository-and-folder-strategy.md`. Each feature owns its
components, hooks, API calls and types.

**Does not own:** cross-feature UI primitives (`../shared`), routing and layout
(`../app`), or framework-adjacent integrations (`../lib`). Features do not
import from each other — shared code moves to `shared/` instead.

Shipped so far: `identity` (register/login/logout, session), `graph` (project
workspace: projects, artifacts, versions, links, approvals), `discovery`
(sessions, questions, responses, assumptions, constraints), `requirements`
(documents, requirements with acceptance criteria, approvals).
