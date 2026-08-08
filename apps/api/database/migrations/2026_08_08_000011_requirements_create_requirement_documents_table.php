<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RequirementDocument, the aggregate root of the Requirements context
 * (docs/product/03-core-modules-and-scope.md, C3). project_id is a real
 * foreign key -- Project is shared-kernel (D-463), same as Discovery's
 * discovery_sessions table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirement_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('project_id')->constrained('projects');
            $table->string('type');
            $table->string('title');
            $table->string('status');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'project_id']);
        });

        DB::statement('ALTER TABLE requirement_documents ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON requirement_documents
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_documents');
    }
};
