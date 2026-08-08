<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TenantProviderPolicy (D-559): governance filter data, one row per
 * restricted tenant. tenant_id is the primary key -- see
 * app/AiOrchestration/Domain/TenantProviderPolicy.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_provider_policies', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->primary()->constrained('tenants');
            $table->jsonb('allowed_providers');
            $table->timestamp('created_at');
        });

        DB::statement('ALTER TABLE tenant_provider_policies ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON tenant_provider_policies
                USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_provider_policies');
    }
};
