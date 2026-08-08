<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ArtifactVersion (immutable child entity of Artifact, D-336). Replaces
 * the Phase 0 graph traversal benchmark's throwaway tables
 * (docs/governance/22-graph-traversal-benchmark-results.md: "deleted once
 * Phase 1's real Delivery Graph kernel lands") -- dropped here,
 * graph_poc_links first since it has a foreign key into graph_poc_versions.
 *
 * `tenant_id` is duplicated from the parent Artifact even though it does
 * not appear on ArtifactVersion in the domain model
 * (docs/architecture/design/32-domain-model-and-ddd.md's diagram omits it,
 * since a child entity is reached through its aggregate root). D-53
 * requires it on every tenant-scoped table regardless: RLS is a
 * table-level, not aggregate-level, mechanism, and this table needs its
 * own policy. The Domain entity (app/Graph/Domain/ArtifactVersion.php)
 * does not carry a tenantId property -- this is a physical-schema
 * necessity, not a domain concept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('graph_poc_links');
        Schema::dropIfExists('graph_poc_versions');

        Schema::create('artifact_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('artifact_id')->constrained('artifacts');
            $table->unsignedInteger('version_number');
            $table->text('content');
            $table->string('lineage_model')->nullable();
            $table->string('lineage_prompt_version')->nullable();
            $table->jsonb('lineage_input_version_ids')->default('[]');
            $table->unsignedInteger('lineage_tokens')->nullable();
            $table->decimal('lineage_cost', 10, 4)->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['artifact_id', 'version_number']);
            $table->index('tenant_id');
        });

        Schema::table('artifacts', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('artifact_versions');
        });

        DB::statement('ALTER TABLE artifact_versions ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON artifact_versions
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('artifact_versions');
    }
};
