<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Model Registry (docs/architecture/ai/50-ai-platform-and-multi-provider.md):
 * "the single catalogue of what may be called, and the source of truth
 * for routing." Deliberately NOT tenant-scoped and NOT RLS-protected --
 * this is platform-wide configuration, not tenant data (there is nothing
 * to isolate between tenants; every tenant sees the same registry,
 * filtered only by their own provider policy at the Application layer).
 * Deliberately has no write path from any controller (D-280: "reviewed
 * like code, not runtime-editable") -- only RegisterModel, called from a
 * seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_registry_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('model_id')->unique();
            $table->string('provider');
            $table->string('tier');
            $table->string('structured_output');
            $table->string('tool_use');
            $table->string('streaming');
            $table->boolean('vision')->default(false);
            $table->boolean('deterministic_seed')->default(false);
            $table->unsignedInteger('context_window');
            $table->unsignedInteger('max_output');
            $table->decimal('input_per_million_tokens_usd', 10, 4);
            $table->decimal('output_per_million_tokens_usd', 10, 4);
            $table->decimal('cached_input_per_million_tokens_usd', 10, 4);
            $table->string('status');
            $table->timestamp('created_at');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_registry_entries');
    }
};
