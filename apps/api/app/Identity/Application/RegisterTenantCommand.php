<?php

declare(strict_types=1);

namespace App\Identity\Application;

final class RegisterTenantCommand
{
    public function __construct(
        public readonly string $tenantName,
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly string $ownerPassword,
    ) {}
}
