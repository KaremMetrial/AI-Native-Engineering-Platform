<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * Tenant-level roles (docs/architecture/07-multi-tenancy-strategy.md, RBAC
 * — "the common case"). Project-level overrides and ABAC (P1/P8/value-based
 * approval) are out of scope for Phase 1: there is no Project entity yet,
 * and no persona currently needs it (07's own principle: ABAC is adopted
 * only because concrete requirements demand it, not for generality).
 */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case DeliveryManager = 'delivery_manager';
    case Architect = 'architect';
    case Contributor = 'contributor';
    case Viewer = 'viewer';

    /**
     * Whether this role may manage other members' roles within a tenant.
     */
    public function managesMembers(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
