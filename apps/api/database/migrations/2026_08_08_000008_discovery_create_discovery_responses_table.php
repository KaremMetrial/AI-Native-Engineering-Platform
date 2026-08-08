<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Response (separate aggregate, bound to a Question -- see
 * app/Discovery/Domain/Response.php). More than one response per
 * question is allowed (multiple stakeholders may answer the same
 * question), so there is no uniqueness constraint on question_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('question_id')->constrained('discovery_questions');
            $table->text('content');
            $table->foreignUuid('responded_by')->constrained('users');
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'question_id']);
        });

        DB::statement('ALTER TABLE discovery_responses ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON discovery_responses
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_responses');
    }
};
