#!/usr/bin/env bash
# One-command local environment bootstrap -- Phase 0 deliverable
# (docs/governance/20-roadmap.md). Idempotent: safe to re-run.
#
# Prefers Docker if available (the documented default path -- see
# infra/docker/docker-compose.yml). Falls back to native services, which is
# what this repository's own development sandbox uses, since Docker's daemon
# is not available there.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="${REPO_ROOT}/apps/api"

echo "== Platform bootstrap =="

# --- 1. Services -------------------------------------------------------
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    echo "-- Docker available: starting services via docker compose"
    (cd "${REPO_ROOT}/infra/docker" && docker compose up -d postgres redis)
else
    echo "-- Docker unavailable: starting Postgres and Redis natively"
    service postgresql start >/dev/null 2>&1 || true
    (redis-cli ping >/dev/null 2>&1) || redis-server --daemonize yes --port 6379

    for i in $(seq 1 10); do
        pg_isready >/dev/null 2>&1 && break
        sleep 1
    done
fi

# --- 2. Database roles and databases (idempotent) -----------------------
# platform_app: runtime role, no BYPASSRLS, does not own tables -- D-55.
# platform_migrator: privileged role, owns and creates schema objects.
echo "-- Ensuring database roles and databases exist"
su postgres -c "psql -v ON_ERROR_STOP=1 -q" <<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'platform_migrator') THEN
        CREATE ROLE platform_migrator WITH LOGIN PASSWORD 'migrator_local_dev_only' CREATEDB;
    END IF;
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'platform_app') THEN
        CREATE ROLE platform_app WITH LOGIN PASSWORD 'app_local_dev_only'
            NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
    END IF;
END
$$;
SELECT 'CREATE DATABASE platform_dev OWNER platform_migrator'
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'platform_dev')
\gexec
SELECT 'CREATE DATABASE platform_test OWNER platform_migrator'
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'platform_test')
\gexec
SQL

for db in platform_dev platform_test; do
    su postgres -c "psql -d ${db} -v ON_ERROR_STOP=1 -q" <<SQL
GRANT CONNECT ON DATABASE ${db} TO platform_app;
GRANT USAGE ON SCHEMA public TO platform_app;
ALTER DEFAULT PRIVILEGES FOR ROLE platform_migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO platform_app;
ALTER DEFAULT PRIVILEGES FOR ROLE platform_migrator IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO platform_app;
SQL
done

# --- 3. apps/api --------------------------------------------------------
echo "-- apps/api: installing dependencies"
(cd "$API_DIR" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction)

if [ ! -f "${API_DIR}/.env" ]; then
    cp "${API_DIR}/.env.example" "${API_DIR}/.env"
fi

if [ ! -f "${API_DIR}/.tools/phpstan.phar" ]; then
    "${REPO_ROOT}/tools/phpstan/install.sh"
fi

(cd "$API_DIR" && php artisan key:generate --ansi)
echo "-- apps/api: running migrations (privileged connection)"
(cd "$API_DIR" && php artisan migrate --database=pgsql_migrate --force)
(cd "$API_DIR" && DB_DATABASE=platform_test php artisan migrate --database=pgsql_migrate --force)

# --- 4. apps/web ---------------------------------------------------------
echo "-- apps/web: installing dependencies"
(cd "${REPO_ROOT}/apps/web" && npm install)

# --- 5. apps/ai -----------------------------------------------------------
echo "-- apps/ai: creating venv and installing dependencies"
if [ ! -d "${REPO_ROOT}/apps/ai/.venv" ]; then
    python3 -m venv "${REPO_ROOT}/apps/ai/.venv"
fi
(cd "${REPO_ROOT}/apps/ai" && . .venv/bin/activate && pip install --quiet --upgrade pip && pip install --quiet -e ".[dev]")

echo "== Bootstrap complete =="
echo "  apps/api : cd apps/api && composer check"
echo "  apps/web : cd apps/web && npm run lint && npm test && npm run build"
echo "  apps/ai  : cd apps/ai && . .venv/bin/activate && ruff check . && mypy src && pytest"
