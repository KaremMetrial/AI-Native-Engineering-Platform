<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Harness proof: the app connects to real PostgreSQL as the unprivileged
 * `platform_app` role (D-55) and can perform ordinary CRUD.
 *
 * Deliberately uses DatabaseTransactions, not RefreshDatabase: the runtime
 * connection has no CREATE privilege on the schema (verified in
 * docs/architecture/07-multi-tenancy-strategy.md's isolation model), so it
 * cannot run migrations. Migrations run once, out of band, via the separate
 * `pgsql_migrate` connection -- `php artisan migrate --database=pgsql_migrate`.
 */
class DatabaseConnectionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_connects_to_postgres_and_can_read_write(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        $id = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Harness Probe',
            'email' => 'harness-probe@example.test',
            'password' => 'unused',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('users', ['id' => $id, 'email' => 'harness-probe@example.test']);
    }

    public function test_runtime_role_cannot_alter_schema(): void
    {
        // Verifies D-55 through the application's own connection, not just
        // a manual psql probe: the runtime role must not be able to create
        // tables, or RLS's database-level enforcement has no teeth.
        $this->expectException(QueryException::class);

        DB::statement('CREATE TABLE runtime_role_should_not_create_this (id serial)');
    }
}
