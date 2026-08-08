<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\MembershipRepository;
use App\Identity\Domain\TenantRepository;
use App\Identity\Domain\TokenIssuer;
use App\Identity\Domain\UserRepository;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantRepository::class, EloquentTenantRepository::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(MembershipRepository::class, EloquentMembershipRepository::class);
        $this->app->bind(TokenIssuer::class, SanctumTokenIssuer::class);
    }
}
