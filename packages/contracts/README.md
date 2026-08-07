# Contracts

The OpenAPI specification and generated client types -- the single source of
truth for the API contract (D-110). Both `apps/web` and `apps/ai` will
consume generated types from here rather than hand-writing them, so drift
becomes a build failure instead of a production defect.

**Empty until the first endpoint exists.** Generating a contract for
endpoints that don't exist yet would be exactly the kind of fake
implementation the charter forbids -- this directory is populated as
`apps/api`'s first real routes land, not before.
