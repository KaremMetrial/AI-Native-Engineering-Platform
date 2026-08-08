<?php

declare(strict_types=1);

namespace Tests\Integration\Identity;

use App\Identity\Domain\Membership;
use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\EloquentMembershipRepository;
use App\Identity\Infrastructure\EloquentTenantRepository;
use App\Identity\Infrastructure\EloquentUserRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the repository against real RLS -- see tests/Isolation/MembershipIsolationTest.php
 * for the dedicated cross-tenant proof suite. This file only checks the
 * repository's own read/write mapping works when correctly scoped.
 */
class EloquentMembershipRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantContext = new TenantContext;
    }

    protected function tearDown(): void
    {
        $this->tenantContext->clear();
        parent::tearDown();
    }

    public function test_save_then_find_by_tenant_and_user_round_trips_a_membership(): void
    {
        $tenantId = $this->createTenant();
        $userId = $this->createUser();
        $this->tenantContext->bind($tenantId);

        $repository = new EloquentMembershipRepository;
        $membership = Membership::create(id: (string) Str::uuid(), tenantId: $tenantId, userId: $userId, role: Role::Contributor);
        $repository->save($membership);

        $found = $repository->findByTenantAndUser($tenantId, $userId);

        $this->assertNotNull($found);
        $this->assertSame(Role::Contributor, $found->role());
    }

    public function test_find_by_user_sees_memberships_across_tenants_without_a_tenant_bound(): void
    {
        $userId = $this->createUser();
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $repository = new EloquentMembershipRepository;

        $this->tenantContext->bind($tenantA);
        $repository->save(Membership::create(id: (string) Str::uuid(), tenantId: $tenantA, userId: $userId, role: Role::Owner));

        $this->tenantContext->bind($tenantB);
        $repository->save(Membership::create(id: (string) Str::uuid(), tenantId: $tenantB, userId: $userId, role: Role::Viewer));

        // The exact bootstrapping scenario this carve-out exists for:
        // no tenant bound, only the acting user.
        $this->tenantContext->clear();
        $this->tenantContext->bindActingUser($userId);

        $memberships = $repository->findByUser($userId);

        $this->assertCount(2, $memberships);
    }

    private function createTenant(): string
    {
        $tenant = Tenant::provision(id: (string) Str::uuid(), name: 'Acme', plan: 'trial', region: 'us');
        (new EloquentTenantRepository)->save($tenant);

        return $tenant->id;
    }

    private function createUser(): string
    {
        $user = User::register(
            id: (string) Str::uuid(),
            name: 'Ada',
            email: Str::uuid().'@example.test',
            hashedPassword: 'hashed',
        );
        (new EloquentUserRepository)->save($user);

        return $user->id;
    }
}
