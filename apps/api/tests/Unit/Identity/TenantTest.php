<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantStatus;
use DomainException;
use Tests\TestCase;

class TenantTest extends TestCase
{
    public function test_provision_creates_an_active_tenant(): void
    {
        $tenant = Tenant::provision(id: 'tenant-1', name: 'Acme', plan: 'trial', region: 'us');

        $this->assertSame('Acme', $tenant->name());
        $this->assertSame(TenantStatus::Active, $tenant->status());
        $this->assertSame('trial', $tenant->plan);
        $this->assertSame('us', $tenant->region);
    }

    public function test_suspend_transitions_an_active_tenant(): void
    {
        $tenant = Tenant::provision(id: 'tenant-1', name: 'Acme', plan: 'trial', region: 'us');

        $tenant->suspend();

        $this->assertSame(TenantStatus::Suspended, $tenant->status());
    }

    public function test_suspend_twice_throws(): void
    {
        $tenant = Tenant::provision(id: 'tenant-1', name: 'Acme', plan: 'trial', region: 'us');
        $tenant->suspend();

        $this->expectException(DomainException::class);

        $tenant->suspend();
    }

    public function test_reactivate_makes_a_suspended_tenant_active_again(): void
    {
        $tenant = Tenant::provision(id: 'tenant-1', name: 'Acme', plan: 'trial', region: 'us');
        $tenant->suspend();

        $tenant->reactivate();

        $this->assertSame(TenantStatus::Active, $tenant->status());
    }
}
