<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval (separate aggregate, D-335). Binds to a version, never an
 * artifact (D-278).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('artifact_version_id')->constrained('artifact_versions');
            $table->foreignUuid('approved_by')->constrained('users');
            $table->string('decision');
            $table->text('comment')->nullable();
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'artifact_version_id']);
        });

        DB::statement('ALTER TABLE approvals ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON approvals
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
