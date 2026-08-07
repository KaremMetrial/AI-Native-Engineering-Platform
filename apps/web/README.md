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
├── features/   Feature modules (empty until a second feature justifies extraction)
├── shared/     Cross-feature UI and utilities (empty until genuinely shared)
└── lib/        Thin wrappers around external libraries
```

`features/`, `shared/`, and `lib/` each carry a `README.md` explaining why
they're currently empty rather than pre-populated — extraction happens on
the second real occurrence, not speculatively.

## Linting

ESLint (flat config), not Oxlint: `eslint-plugin-jsx-a11y` (accessibility,
blocking per D-133) has no Oxlint equivalent, and it isn't optional here.
