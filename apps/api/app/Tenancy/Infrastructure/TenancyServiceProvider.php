<?php

declare(strict_types=1);

namespace App\Tenancy\Infrastructure;

use Illuminate\Support\ServiceProvider;

/**
 * Binds TenantContext as a singleton: every consumer within one request
 * (middleware, controllers, use cases) must observe the same bound/unbound
 * state. Without this, each injection resolves a fresh instance whose
 * in-memory $tenantId is null even though the underlying Postgres session
 * variable was already set by whoever bound it first.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }
}
