<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * A user's role within a tenant (docs/architecture/data/42-entity-model-and-ownership.md
 * shared kernel). The tenant-scoped table this maps to
 * (Infrastructure/EloquentMembership, table `memberships`) is the first
 * real RLS-enforced table in the platform -- it replaces the throwaway
 * Phase 0 RLS proof of concept. Framework-free per D-130.
 */
final class Membership
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $userId,
        private Role $role,
        private MembershipStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(string $id, string $tenantId, string $userId, Role $role): self
    {
        return new self($id, $tenantId, $userId, $role, MembershipStatus::Active, new DateTimeImmutable);
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function status(): MembershipStatus
    {
        return $this->status;
    }

    /**
     * @throws DomainException when the acting role lacks member-management
     *                         authority (docs/architecture/07-multi-tenancy-strategy.md RBAC).
     */
    public function changeRole(Role $newRole, Role $actingRole): void
    {
        if (! $actingRole->managesMembers()) {
            throw new DomainException('Only Owner or Admin may change a member\'s role.');
        }

        $this->role = $newRole;
    }

    public function suspend(): void
    {
        $this->status = MembershipStatus::Suspended;
    }
}
