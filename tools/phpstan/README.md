# PHPStan (PHAR install)

Run `./tools/phpstan/install.sh` once to fetch `apps/api/.tools/phpstan.phar`
(gitignored, checksum-verified on every install). Analyse with:

```
php apps/api/.tools/phpstan.phar analyse --configuration apps/api/phpstan.neon
```

## Why not Composer

`phpstan/phpstan` has no usable source/VCS install on Packagist -- only a
dist zip served from `api.github.com`. In an environment where that host
isn't reachable, `composer require phpstan/phpstan` fails with no fallback.
The PHAR, served from GitHub's release-assets host, is PHPStan's own
documented alternative install method, not a workaround.

## Known gap: no Larastan, no Deptrac

**This is a tracked, explicit limitation, not a silently lowered standard**
(the charter and D-206 both require relaxed practices to be recorded openly).

- **Larastan** (Laravel-aware PHPStan rules) needs `phpstan/phpstan` as an
  actual Composer package -- its PHP classes extend PHPStan's directly. The
  PHAR is a self-contained, prefixed binary; it cannot serve that role.
  Larastan itself installs fine via git-source (proven working in this
  environment) -- it is blocked only by its dependency on the Composer
  package form of PHPStan.
- **Deptrac** (module boundary / layer-dependency enforcement, per D-225)
  hit the same `api.github.com` wall as a Composer package, and does not
  publish a standalone PHAR at the path checked for the pinned version.

**What this means in practice:** base PHPStan analysis (strict, but without
Laravel-specific magic-method/facade awareness) runs today. Layer-boundary
enforcement (Domain must not depend on Infrastructure, no framework in
Domain, etc. -- D-225's fitness functions) has no automated check yet.

**Resolve by:** installing Larastan and Deptrac normally via Composer from
an environment with full GitHub access (a developer machine, or a CI runner
without this session's repository-scoped access restriction), then removing
this PHAR-based path. Nothing about this setup is meant to be permanent.
