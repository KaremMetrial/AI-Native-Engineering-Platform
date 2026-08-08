<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Identity\Domain\MembershipRepository;
use App\Identity\Infrastructure\EloquentUser;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MeController extends Controller
{
    public function __invoke(Request $request, MembershipRepository $memberships, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        $tenantId = $tenantContext->current();
        $membership = $memberships->findByTenantAndUser($tenantId, $user->id);

        if ($membership === null) {
            return response()->json(['message' => 'No membership found for the bound tenant.'], 403);
        }

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'tenant_id' => $tenantId,
            'role' => $membership->role()->value,
        ]);
    }
}
