<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared-kernel Membership entity (docs/architecture/data/42-entity-model-and-ownership.md):
 * a user's role within a tenant. The first real tenant-scoped, RLS-enforced
 * table in the platform -- replaces the Phase 0 RLS proof of concept
 * (2026_08_07_000001_create_rls_poc_scoped_items_table.php), which is
 * dropped in this same migration per app/Tenancy/README.md's stated
 * intent ("deleted once Phase 1's real Identity module and tenant-scoped
 * tables land").
 *
 * RLS policy and index shape follow exactly what the spike proved:
 * tenant_id-leading, session-variable-scoped, RLS enabled but not FORCE-d
 * (platform_migrator legitimately needs to write across tenants; D-55's
 * boundary is about platform_app, which is never the owner).
 *
 * The read policy additionally allows a user to see their own membership
 * rows across *all* tenants, not just the currently-bound one. This is
 * the resolution to a real bootstrapping problem: "which tenants does
 * this user belong to" must be answerable before any tenant is bound
 * (there is nothing else to bind it from at login), which a purely
 * tenant-scoped policy cannot serve at all. It does not weaken isolation
 * between tenants -- a user's own membership row is data they are
 * already entitled to regardless of which tenant they're currently
 * acting in, and the write policy (WITH CHECK) stays strictly
 * tenant-bound: this carve-out is read-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rls_poc_scoped_items');

        Schema::create('memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('role');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'user_id']);
            $table->index('tenant_id');
        });

        DB::statement('ALTER TABLE memberships ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON memberships
                USING (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    OR user_id = NULLIF(current_setting('app.user_id', true), '')::uuid
                )
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
