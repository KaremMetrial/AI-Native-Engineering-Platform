<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\MembershipRepository;
use App\Identity\Domain\Role;
use RuntimeException;

/**
 * Changes a member's role within a tenant. Authorization (D-55:
 * "authorization is never the only isolation control, it sits above RLS")
 * happens twice here, deliberately: RLS already confines both
 * memberships to the acting tenant (neither lookup can cross a tenant
 * boundary even if the caller tried), and Membership::changeRole()
 * additionally enforces that only Owner/Admin may perform the change --
 * the two checks answer different questions ("is this the right tenant?"
 * vs "is this actor allowed to do this?").
 */
final class AssignMembershipRole
{
    public function __construct(
        private readonly MembershipRepository $memberships,
    ) {}

    public function handle(string $tenantId, string $actingUserId, string $targetUserId, Role $newRole): void
    {
        $actingMembership = $this->memberships->findByTenantAndUser($tenantId, $actingUserId);
        $targetMembership = $this->memberships->findByTenantAndUser($tenantId, $targetUserId);

        if ($actingMembership === null || $targetMembership === null) {
            throw new RuntimeException('Both the acting user and the target user must be members of this tenant.');
        }

        $targetMembership->changeRole($newRole, $actingMembership->role());

        $this->memberships->save($targetMembership);
    }
}
