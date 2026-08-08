<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ArtifactLink (separate aggregate, D-335). Composite indexes are the
 * exact shape docs/architecture/data/45-index-and-partitioning-strategy.md
 * specifies and docs/governance/22-graph-traversal-benchmark-results.md
 * measured (forward: tenant_id, from_version_id, link_type; reverse:
 * tenant_id, to_version_id, link_type) -- this is the real table that
 * benchmark result was proving Postgres could serve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifact_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('from_version_id')->constrained('artifact_versions');
            $table->foreignUuid('to_version_id')->constrained('artifact_versions');
            $table->string('link_type');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'from_version_id', 'link_type'], 'artifact_links_forward_idx');
            $table->index(['tenant_id', 'to_version_id', 'link_type'], 'artifact_links_reverse_idx');
        });

        DB::statement('ALTER TABLE artifact_links ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON artifact_links
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_links');
    }
};
