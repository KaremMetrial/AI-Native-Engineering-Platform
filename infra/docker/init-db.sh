#!/usr/bin/env bash
# Runs once, automatically, on first Postgres container startup (mounted at
# /docker-entrypoint-initdb.d/). Mirrors the role/database setup in
# tools/bootstrap.sh's native fallback exactly -- both paths must produce
# the same D-55 role separation.
set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" <<-SQL
    CREATE ROLE platform_migrator WITH LOGIN PASSWORD 'migrator_local_dev_only' CREATEDB;
    CREATE ROLE platform_app WITH LOGIN PASSWORD 'app_local_dev_only'
        NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;

    CREATE DATABASE platform_dev OWNER platform_migrator;
    CREATE DATABASE platform_test OWNER platform_migrator;
SQL

for db in platform_dev platform_test; do
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$db" <<-SQL
        GRANT CONNECT ON DATABASE ${db} TO platform_app;
        GRANT USAGE ON SCHEMA public TO platform_app;
        ALTER DEFAULT PRIVILEGES FOR ROLE platform_migrator IN SCHEMA public
            GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO platform_app;
        ALTER DEFAULT PRIVILEGES FOR ROLE platform_migrator IN SCHEMA public
            GRANT USAGE, SELECT ON SEQUENCES TO platform_app;
SQL
done
