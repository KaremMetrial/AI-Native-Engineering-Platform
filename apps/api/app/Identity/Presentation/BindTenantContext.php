<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Domain\MembershipRepository;
use App\Identity\Domain\MembershipStatus;
use App\Identity\Infrastructure\EloquentUser;
use App\Tenancy\Infrastructure\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves and binds the tenant for an authenticated request
 * (docs/architecture/07-multi-tenancy-strategy.md, layers 1-3). Runs after
 * Sanctum's auth:sanctum middleware, so $request->user() is already the
 * authenticated EloquentUser.
 *
 * D-56: tenant is never taken from a client-supplied parameter as the
 * *authorizing* fact -- the `X-Tenant-Id` header below is only a
 * disambiguation hint used to pick among tenants the token's own user is
 * already, verifiably, a member of (via findByUser, which the memberships
 * RLS policy's self-membership carve-out makes possible before any tenant
 * is bound). A header claiming a tenant the user has no membership in is
 * rejected, never trusted.
 */
final class BindTenantContext
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new HttpException(401);
        }

        $this->tenantContext->bindActingUser($user->id);

        $active = array_values(array_filter(
            $this->memberships->findByUser($user->id),
            fn ($membership): bool => $membership->status() === MembershipStatus::Active,
        ));

        if ($active === []) {
            throw new HttpException(403, 'This user has no active tenant membership.');
        }

        $requestedTenantId = $request->header('X-Tenant-Id');

        if ($requestedTenantId !== null) {
            $match = array_values(array_filter($active, fn ($m): bool => $m->tenantId === $requestedTenantId));

            if ($match === []) {
                throw new HttpException(403, 'Not a member of the requested tenant.');
            }

            $this->tenantContext->bind($match[0]->tenantId);
        } elseif (count($active) === 1) {
            $this->tenantContext->bind($active[0]->tenantId);
        } else {
            throw new HttpException(409, 'This user belongs to multiple tenants; specify X-Tenant-Id.');
        }

        try {
            return $next($request);
        } finally {
            // D-57/07 connection-pool-leakage: always clear, success or
            // failure, so a pooled connection never carries this request's
            // tenant into the next one.
            $this->tenantContext->clear();
        }
    }
}
