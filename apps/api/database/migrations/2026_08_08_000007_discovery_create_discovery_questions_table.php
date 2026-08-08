<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Question (separate aggregate from DiscoverySession, D-335-style
 * reasoning -- see app/Discovery/Domain/DiscoverySession.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('session_id')->constrained('discovery_sessions');
            $table->text('prompt');
            $table->unsignedInteger('sequence');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'session_id']);
        });

        DB::statement('ALTER TABLE discovery_questions ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON discovery_questions
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_questions');
    }
};
