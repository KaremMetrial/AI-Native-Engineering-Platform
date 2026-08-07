<?php

declare(strict_types=1);

namespace Tests\Isolation;

use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 0 RLS isolation proof of concept (docs/governance/20-roadmap.md
 * exit criteria: "Isolation model proven, including a test that RLS
 * actually blocks a bypass attempt"). See app/Tenancy/README.md and
 * database/migrations/2026_08_07_000001_create_rls_poc_scoped_items_table.php
 * for what this suite runs against and why it is throwaway.
 */
class RlsIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantContext = new TenantContext;
    }

    protected function tearDown(): void
    {
        // Session-level GUCs set via set_config(..., false) are not
        // transaction-scoped and would otherwise leak into the next test
        // sharing this process's connection. Safe to run here because the
        // exception tests below use DB::transaction()'s savepoint handling
        // to recover the outer per-test transaction themselves, rather than
        // leaving it aborted.
        $this->tenantContext->clear();
        DB::statement('SET row_security = on');

        parent::tearDown();
    }

    public function test_a_tenant_only_sees_its_own_rows(): void
    {
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();

        $this->tenantContext->bind($tenantA);
        DB::table('rls_poc_scoped_items')->insert(['tenant_id' => $tenantA, 'label' => 'a-item']);

        $this->tenantContext->bind($tenantB);
        DB::table('rls_poc_scoped_items')->insert(['tenant_id' => $tenantB, 'label' => 'b-item']);

        $this->tenantContext->bind($tenantA);
        $rows = DB::table('rls_poc_scoped_items')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('a-item', $rows->first()->label);
    }

    public function test_a_tenant_cannot_write_a_row_for_another_tenant(): void
    {
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();

        $this->tenantContext->bind($tenantA);

        $this->expectException(QueryException::class);

        // Wrapped in a nested transaction so Postgres recovers via
        // ROLLBACK TO SAVEPOINT on failure, rather than leaving the
        // per-test transaction aborted for every statement after it.
        DB::transaction(function () use ($tenantB): void {
            DB::table('rls_poc_scoped_items')->insert(['tenant_id' => $tenantB, 'label' => 'smuggled']);
        });
    }

    public function test_direct_id_access_to_another_tenants_row_returns_nothing(): void
    {
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();

        $this->tenantContext->bind($tenantA);
        $id = DB::table('rls_poc_scoped_items')->insertGetId(['tenant_id' => $tenantA, 'label' => 'a-item']);

        $this->tenantContext->bind($tenantB);
        $row = DB::table('rls_poc_scoped_items')->where('id', $id)->first();

        $this->assertNull($row);
    }

    public function test_missing_tenant_context_returns_zero_rows_not_another_tenants_data(): void
    {
        $tenantA = (string) Str::uuid();

        $this->tenantContext->bind($tenantA);
        DB::table('rls_poc_scoped_items')->insert(['tenant_id' => $tenantA, 'label' => 'a-item']);
        $this->tenantContext->clear();

        $rows = DB::table('rls_poc_scoped_items')->get();

        $this->assertCount(0, $rows);
    }

    public function test_row_security_off_bypass_attempt_is_blocked(): void
    {
        $tenantA = (string) Str::uuid();
        $this->tenantContext->bind($tenantA);
        DB::table('rls_poc_scoped_items')->insert(['tenant_id' => $tenantA, 'label' => 'a-item']);

        DB::statement('SET row_security = off');

        // platform_app has neither BYPASSRLS nor table ownership (D-55),
        // so Postgres refuses to run the query at all rather than silently
        // ignoring the policy -- the query errors instead of leaking rows.
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::table('rls_poc_scoped_items')->get();
        });
    }

    public function test_runtime_role_cannot_disable_row_level_security(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE rls_poc_scoped_items DISABLE ROW LEVEL SECURITY');
        });
    }

    public function test_runtime_role_has_neither_bypassrls_nor_table_ownership(): void
    {
        $role = DB::selectOne(
            'SELECT rolbypassrls, rolsuper FROM pg_roles WHERE rolname = current_user',
        );

        $this->assertFalse((bool) $role->rolbypassrls, 'platform_app must not have BYPASSRLS (D-55)');
        $this->assertFalse((bool) $role->rolsuper, 'platform_app must not be a superuser (D-55)');

        $owner = DB::selectOne(
            "SELECT tableowner FROM pg_tables WHERE tablename = 'rls_poc_scoped_items'",
        );

        $this->assertNotSame('platform_app', $owner->tableowner, 'platform_app must not own the table (D-55)');
    }

    public function test_context_does_not_leak_across_a_simulated_connection_checkout_cycle(): void
    {
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();

        $this->tenantContext->bind($tenantA);
        DB::table('rls_poc_scoped_items')->insert(['tenant_id' => $tenantA, 'label' => 'a-item']);

        // Simulate release-then-checkout of a pooled connection: clear,
        // then bind the next request's tenant.
        $this->tenantContext->clear();
        $this->tenantContext->bind($tenantB);
        DB::table('rls_poc_scoped_items')->insert(['tenant_id' => $tenantB, 'label' => 'b-item']);

        $rows = DB::table('rls_poc_scoped_items')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('b-item', $rows->first()->label);
    }
}
