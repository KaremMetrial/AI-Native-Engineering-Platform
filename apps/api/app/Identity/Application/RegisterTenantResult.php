<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Membership;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\User;

final class RegisterTenantResult
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $owner,
        public readonly Membership $membership,
        public readonly string $token,
    ) {}
}
