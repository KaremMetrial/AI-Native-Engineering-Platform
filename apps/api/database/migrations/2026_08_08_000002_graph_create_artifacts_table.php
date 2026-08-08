<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Artifact (aggregate root, docs/architecture/design/32-domain-model-and-ddd.md):
 * "a thin mutable pointer to the current version plus stable identity."
 * `current_version_id` has no FK yet -- artifact_versions doesn't exist
 * until the next migration, and the two tables reference each other. The
 * FK is added there once both tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('project_id')->constrained('projects');
            $table->string('type');
            $table->uuid('current_version_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'project_id', 'type', 'status']);
        });

        DB::statement('ALTER TABLE artifacts ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON artifacts
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};
