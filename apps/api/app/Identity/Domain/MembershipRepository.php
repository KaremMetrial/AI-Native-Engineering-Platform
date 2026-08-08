<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface MembershipRepository
{
    public function save(Membership $membership): void;

    public function findByTenantAndUser(string $tenantId, string $userId): ?Membership;

    /**
     * @return list<Membership>
     */
    public function findByUser(string $userId): array;
}
