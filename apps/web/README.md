# apps/web

React 19 + TypeScript (strict) + Vite SPA.

## Commands

See the root [`README.md`](../../README.md#development) for the full,
CI-verified command set. Short version:

```bash
npm run lint       # ESLint: strictTypeChecked + jsx-a11y (blocking, D-133) + react-hooks
npm run typecheck  # tsc -b --noEmit, strict mode (D-132: no `any`)
npm test           # Vitest + React Testing Library
npm run build      # tsc -b && vite build
```

## Structure

```
src/
├── app/        Application shell and routing
├── features/   Feature modules, one per bounded context (identity, graph, ...)
├── shared/     Cross-feature UI and utilities (API client, auth, TextField, test utils)
└── lib/        Thin wrappers around external libraries (empty until genuinely needed)
```

`features/`, `shared/`, and `lib/` each carry a `README.md` explaining their
scope and boundary rules. Code moves into `shared/` on its second real use
by a different feature, not speculatively — `TextField` and the test render
helpers made that move when `graph` needed what `identity` had already
built.

## Linting

ESLint (flat config), not Oxlint: `eslint-plugin-jsx-a11y` (accessibility,
blocking per D-133) has no Oxlint equivalent, and it isn't optional here.

## Frontend Conventions

`docs/architecture/06-technology-decisions.md` picks React + TypeScript +
Vite but leaves routing, data fetching and styling as "decided once, in
the frontend conventions doc" -- this is that doc. Each choice below is
made once here rather than re-litigated per feature.

| Concern                      | Choice                                                                                                                                                                 | Why                                                                                                                                                                                                                                                                                                                  |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| API contract                 | Generated from `packages/contracts/openapi.json` (`npm run contract:types` → `src/shared/api/schema.ts`)                                                               | D-110: hand-written types drift from the server; generation makes drift a build failure                                                                                                                                                                                                                              |
| HTTP client                  | `openapi-fetch` (`src/shared/api/client.ts`)                                                                                                                           | A typed thin wrapper, not a hand-rolled one -- request/response types come directly from the generated schema, no duplication                                                                                                                                                                                        |
| Auth                         | Sanctum bearer token in `localStorage` (`src/shared/auth/token.ts`), attached by the client's auth middleware                                                          | The backend issues plain bearer tokens (`LoginController`/`RegisterController`), not cookie-based stateful sessions -- see `client.ts`'s docblock                                                                                                                                                                    |
| Routing                      | `react-router-dom` (`createBrowserRouter`/`RouterProvider`), one route tree in `src/app/router.tsx`, extended by each feature as it ships                              | A single source of truth for the route tree, matching D-12 (features are bounded contexts; routing composes them, it does not own them)                                                                                                                                                                              |
| Data fetching / server cache | `@tanstack/react-query`, one `QueryClient` in `src/app/queryClient.ts`                                                                                                 | Avoids hand-rolled loading/error/cache state per feature; pairs directly with the typed `apiClient`                                                                                                                                                                                                                  |
| Forms                        | Plain controlled components + `zod` for validation, no form library                                                                                                    | The forms this platform needs so far are a handful of fields each; a form library is worth adopting the day a feature needs field arrays, complex cross-field validation, or performance-sensitive re-render control -- not before (sibling-occurrence principle, D-577, applied here as it is to shared components) |
| Styling                      | CSS Modules (`*.module.css`, Vite-native, zero extra dependency), building on the CSS custom properties already in `index.css` (light/dark via `prefers-color-scheme`) | No design system doc exists yet to justify a component library or utility framework; CSS Modules keep styles scoped and reversible without committing to either before real screens exist                                                                                                                            |

**Regenerating the contract:** after any backend route/request/response
change, run `composer contract:export` in `apps/api`, then
`npm run contract:types` here, and commit both. Two CI steps enforce this
can't silently drift: `apps/api`'s "Verify OpenAPI contract is up to
date" catches a stale `openapi.json`, and this app's "Verify generated
API types are up to date" catches a stale `schema.ts` relative to it.
