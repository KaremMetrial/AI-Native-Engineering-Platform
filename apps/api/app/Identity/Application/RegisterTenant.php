<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Membership;
use App\Identity\Domain\MembershipRepository;
use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantRepository;
use App\Identity\Domain\TokenIssuer;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Registers a new tenant with its owning user, atomically (docs/architecture/07-multi-tenancy-strategy.md
 * Tenant Lifecycle: "Provisioning: Atomic: tenant, owner, defaults,
 * entitlements. Partial provisioning leaves an unusable account.").
 *
 * Uses Illuminate\Support\Facades\DB directly for the transaction boundary
 * -- Application layers are not required to be framework-free (only
 * Domain is, D-130/docs/delivery/11); a dedicated transaction-manager port
 * would be over-abstraction for the one place this is needed.
 */
final class RegisterTenant
{
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly UserRepository $users,
        private readonly MembershipRepository $memberships,
        private readonly TokenIssuer $tokens,
        private readonly Hasher $hasher,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(RegisterTenantCommand $command): RegisterTenantResult
    {
        if ($this->users->existsByEmail($command->ownerEmail)) {
            throw new RuntimeException('A user with this email already exists.');
        }

        $result = DB::transaction(function () use ($command): RegisterTenantResult {
            $tenant = Tenant::provision(
                id: (string) Str::uuid(),
                name: $command->tenantName,
                plan: 'trial',
                region: 'us',
            );
            $this->tenants->save($tenant);

            // The membership row about to be created is scoped to this
            // brand-new tenant, so RLS's WITH CHECK needs it bound before
            // that insert -- there is no earlier point at which it could
            // have been known.
            $this->tenantContext->bind($tenant->id);

            $owner = User::register(
                id: (string) Str::uuid(),
                name: $command->ownerName,
                email: $command->ownerEmail,
                hashedPassword: $this->hasher->make($command->ownerPassword),
            );
            $this->users->save($owner);
            $this->tenantContext->bindActingUser($owner->id);

            $membership = Membership::create(
                id: (string) Str::uuid(),
                tenantId: $tenant->id,
                userId: $owner->id,
                role: Role::Owner,
            );
            $this->memberships->save($membership);

            $token = $this->tokens->issue($owner);

            return new RegisterTenantResult($tenant, $owner, $membership, $token);
        });

        if (! $result instanceof RegisterTenantResult) {
            throw new RuntimeException('Unexpected transaction result type.');
        }

        return $result;
    }
}
