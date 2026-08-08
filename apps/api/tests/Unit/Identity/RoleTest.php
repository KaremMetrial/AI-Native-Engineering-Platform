<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Identity\Domain\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    public function test_owner_and_admin_manage_members(): void
    {
        $this->assertTrue(Role::Owner->managesMembers());
        $this->assertTrue(Role::Admin->managesMembers());
    }

    public function test_other_roles_do_not_manage_members(): void
    {
        $this->assertFalse(Role::DeliveryManager->managesMembers());
        $this->assertFalse(Role::Architect->managesMembers());
        $this->assertFalse(Role::Contributor->managesMembers());
        $this->assertFalse(Role::Viewer->managesMembers());
    }
}
