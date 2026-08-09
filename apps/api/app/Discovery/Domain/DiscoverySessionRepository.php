<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

interface DiscoverySessionRepository
{
    public function save(DiscoverySession $session): void;

    public function findById(string $id): ?DiscoverySession;

    /**
     * Every session visible to the acting tenant -- tenant scoping comes
     * from RLS (bound via TenantContext), the same as findById, not an
     * explicit where clause here.
     *
     * @return list<DiscoverySession>
     */
    public function findAll(): array;
}
