<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Identity\Domain\Membership;
use App\Identity\Domain\MembershipStatus;
use App\Identity\Domain\Role;
use DomainException;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    public function test_create_makes_an_active_membership_with_the_given_role(): void
    {
        $membership = Membership::create(id: 'm-1', tenantId: 't-1', userId: 'u-1', role: Role::Contributor);

        $this->assertSame(Role::Contributor, $membership->role());
        $this->assertSame(MembershipStatus::Active, $membership->status());
    }

    public function test_owner_can_change_another_members_role(): void
    {
        $membership = Membership::create(id: 'm-1', tenantId: 't-1', userId: 'u-1', role: Role::Viewer);

        $membership->changeRole(Role::Architect, actingRole: Role::Owner);

        $this->assertSame(Role::Architect, $membership->role());
    }

    public function test_admin_can_change_another_members_role(): void
    {
        $membership = Membership::create(id: 'm-1', tenantId: 't-1', userId: 'u-1', role: Role::Viewer);

        $membership->changeRole(Role::Contributor, actingRole: Role::Admin);

        $this->assertSame(Role::Contributor, $membership->role());
    }

    public function test_a_contributor_cannot_change_a_members_role(): void
    {
        $membership = Membership::create(id: 'm-1', tenantId: 't-1', userId: 'u-1', role: Role::Viewer);

        $this->expectException(DomainException::class);

        $membership->changeRole(Role::Architect, actingRole: Role::Contributor);
    }

    public function test_suspend_marks_the_membership_suspended(): void
    {
        $membership = Membership::create(id: 'm-1', tenantId: 't-1', userId: 'u-1', role: Role::Viewer);

        $membership->suspend();

        $this->assertSame(MembershipStatus::Suspended, $membership->status());
    }
}
