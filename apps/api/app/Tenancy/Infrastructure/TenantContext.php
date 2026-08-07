<?php

declare(strict_types=1);

namespace App\Tenancy\Infrastructure;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Binds the current tenant onto the database connection's session state
 * (docs/architecture/07-multi-tenancy-strategy.md, layer 3). RLS policies
 * (layer 4) read this back via `current_setting('app.tenant_id', true)`.
 *
 * Session-scoped, not transaction-scoped: it must survive across multiple
 * queries within one request/connection checkout and be explicitly cleared
 * on release, per 07's connection-pool-leakage failure mode.
 */
final class TenantContext
{
    private ?string $tenantId = null;

    public function bind(string $tenantId): void
    {
        DB::statement('SELECT set_config(?, ?, false)', ['app.tenant_id', $tenantId]);
        $this->tenantId = $tenantId;
    }

    public function clear(): void
    {
        DB::statement('SELECT set_config(?, ?, false)', ['app.tenant_id', '']);
        $this->tenantId = null;
    }

    /**
     * @throws RuntimeException when no tenant is bound. D-57: a missing
     *                          tenant context is a fatal error, never a fallback.
     */
    public function current(): string
    {
        if ($this->tenantId === null) {
            throw new RuntimeException(
                'No tenant is bound to the current context (D-57: fail closed, never fall back).',
            );
        }

        return $this->tenantId;
    }

    public function bound(): bool
    {
        return $this->tenantId !== null;
    }
}
