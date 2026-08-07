<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 RLS isolation proof of concept (docs/governance/20-roadmap.md).
 *
 * This table is throwaway: it exists only to prove the Postgres RLS
 * mechanism (docs/architecture/07-multi-tenancy-strategy.md, D-54/D-55)
 * before any real schema depends on it. It is deliberately NOT the
 * shared-kernel Tenant/Membership entities
 * (docs/architecture/data/42-entity-model-and-ownership.md) -- those are
 * gated behind the metrial-auth evaluation ADR (D-20, ADR-0001, still
 * open). See app/Tenancy/README.md.
 *
 * Deleted once Phase 1's real Identity module and tenant-scoped tables
 * land.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rls_poc_scoped_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('label');
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Deliberately no FORCE ROW LEVEL SECURITY: the D-55 boundary this
        // proves is about platform_app (the runtime role, which is never
        // the owner and so is never exempt regardless of FORCE), not about
        // platform_migrator. Migrator is the table owner and legitimately
        // needs to bypass RLS for administrative/seeding writes; FORCE
        // would block that without adding any protection platform_app
        // doesn't already have.
        DB::statement('ALTER TABLE rls_poc_scoped_items ENABLE ROW LEVEL SECURITY');

        // NULLIF(...,'')::uuid: an unset or explicitly-cleared session
        // variable casts to NULL, and `tenant_id = NULL` is never true --
        // so a missing tenant context returns zero rows, not another
        // tenant's data (07's central claim about layer 4).
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON rls_poc_scoped_items
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('rls_poc_scoped_items');
    }
};
