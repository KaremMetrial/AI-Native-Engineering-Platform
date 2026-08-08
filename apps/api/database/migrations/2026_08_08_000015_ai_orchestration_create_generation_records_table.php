<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GenerationRecord -- the P-9 sync/async boundary made concrete. See
 * app/AiOrchestration/Domain/GenerationRecord.php for why this stops at
 * candidate selection rather than modelling a full dispatch lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('workflow_name');
            $table->jsonb('capability_requirement');
            $table->foreignUuid('requested_by')->constrained('users');
            $table->string('status');
            $table->string('selected_model_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'workflow_name']);
        });

        DB::statement('ALTER TABLE generation_records ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON generation_records
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_records');
    }
};
