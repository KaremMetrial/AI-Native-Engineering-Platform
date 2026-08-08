<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Requirement (separate aggregate from RequirementDocument -- see
 * app/Requirements/Domain/RequirementDocument.php docblock).
 * acceptance_criteria is jsonb: the AcceptanceCriterion value objects have
 * no identity or lifecycle of their own, same reasoning as
 * artifact_versions.lineage_input_version_ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('document_id')->constrained('requirement_documents');
            $table->text('text');
            $table->jsonb('acceptance_criteria')->default('[]');
            $table->string('status');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'document_id']);
        });

        DB::statement('ALTER TABLE requirements ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON requirements
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};
