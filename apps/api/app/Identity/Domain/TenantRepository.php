<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface TenantRepository
{
    public function save(Tenant $tenant): void;

    public function findById(string $id): ?Tenant;
}
