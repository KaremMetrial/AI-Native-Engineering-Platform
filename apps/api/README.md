# apps/api

Laravel 13 (PHP 8.4) core API — modular monolith per
[`docs/delivery/11-repository-and-folder-strategy.md`](../../docs/delivery/11-repository-and-folder-strategy.md).

Organized by bounded context, then by layer (`app/Modules/<Context>/{Domain,
Application,Infrastructure,Presentation}`), not by Laravel's default
technical grouping — see `11` for why. `app/Tenancy/` holds tenant
resolution and RLS binding, real and reusable regardless of the identity
package decision (see `app/Tenancy/README.md`).

## Commands

See the root [`README.md`](../../README.md#development) for the full,
CI-verified command set. Short version:

```bash
composer check   # Pint format check + PHPStan (max) + PHPUnit (5 suites)
```

## Database

Postgres only (D-138 — SQLite cannot enforce Row-Level Security, which is
how tenant isolation is enforced, D-54). Two connections, two roles:
`pgsql` (`platform_app`, the runtime role — no `BYPASSRLS`, doesn't own any
table) and `pgsql_migrate` (`platform_migrator`, privileged, runs
migrations). See
[`docs/architecture/07-multi-tenancy-strategy.md`](../../docs/architecture/07-multi-tenancy-strategy.md).

## Tests

Five suites, run via `composer test` or `php artisan test`:

| Suite | Mirrors |
| --- | --- |
| `Unit` | `Modules/*/Domain` |
| `Integration` | `Modules/*/Infrastructure` |
| `Feature` | API-level behavior |
| `Architecture` | Boundary and layering rules |
| `Isolation` | Tenant isolation (T-1) — see `tests/Isolation/README.md` |

## Known gap

Static analysis runs PHPStan directly via a checksum-pinned PHAR
(`tools/phpstan/install.sh`), not through Composer, because this
development environment's GitHub access is scoped in a way that blocks
`composer require phpstan/phpstan`. Larastan and Deptrac are not installed
as a result — tracked openly in `tools/phpstan/README.md` (D-206), not
silently dropped. Both install normally in an environment with full GitHub
access, including this project's own CI.
