<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared-kernel Tenant entity (docs/architecture/data/42-entity-model-and-ownership.md).
 * The isolation root: no tenant_id column, no RLS policy -- every other
 * tenant-scoped table (starting with `memberships`, next migration)
 * references this table's id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('plan')->default('trial');
            $table->string('region')->default('us');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
