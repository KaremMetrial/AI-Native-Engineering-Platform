<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 graph-traversal benchmark (docs/governance/20-roadmap.md, TR-2,
 * P-3: "Graph traversal (impact analysis, depth <= 5), p95 < 1s").
 *
 * Throwaway, same discipline as the RLS proof of concept
 * (2026_08_07_000001_...): these are not the real Delivery Graph kernel
 * (Artifact/ArtifactVersion/ArtifactLink, docs/architecture/data/
 * 42-entity-model-and-ownership.md), which lands with Phase 1's real
 * design. This exists only to measure whether Postgres meets P-3 at
 * realistic volume, using the exact index shape
 * docs/architecture/data/45-index-and-partitioning-strategy.md specifies
 * (forward: tenant_id, from_version_id, link_type; reverse: tenant_id,
 * to_version_id, link_type) and with RLS enabled, per D-54's requirement
 * that performance be benchmarked with policies active, not disabled for
 * convenience.
 *
 * Deleted once Phase 1's real Delivery Graph kernel lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_poc_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('label');
            $table->timestamps();

            $table->index('tenant_id');
        });

        Schema::create('graph_poc_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->foreignId('from_version_id')->constrained('graph_poc_versions');
            $table->foreignId('to_version_id')->constrained('graph_poc_versions');
            $table->string('link_type')->default('implements');
            $table->timestamps();

            $table->index(['tenant_id', 'from_version_id', 'link_type'], 'graph_poc_links_forward_idx');
            $table->index(['tenant_id', 'to_version_id', 'link_type'], 'graph_poc_links_reverse_idx');
        });

        foreach (['graph_poc_versions', 'graph_poc_links'] as $table) {
            // Deliberately no FORCE ROW LEVEL SECURITY -- see
            // 2026_08_07_000001_create_rls_poc_scoped_items_table.php for
            // why: the D-55 boundary is about platform_app, not the
            // platform_migrator owner, which needs to bulk-seed synthetic
            // data across many tenants for this benchmark.
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                    USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_poc_links');
        Schema::dropIfExists('graph_poc_versions');
    }
};
