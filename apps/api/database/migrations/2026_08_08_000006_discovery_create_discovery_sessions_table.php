<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DiscoverySession, the aggregate root of a discovery engagement
 * (docs/product/03-core-modules-and-scope.md, C2). project_id is a real
 * foreign key -- Project is shared-kernel, not cross-context, so D-463
 * applies rather than D-461's plain-identifier rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('project_id')->constrained('projects');
            $table->string('title');
            $table->string('status');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at');
            $table->timestamp('completed_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'project_id']);
        });

        DB::statement('ALTER TABLE discovery_sessions ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON discovery_sessions
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_sessions');
    }
};
