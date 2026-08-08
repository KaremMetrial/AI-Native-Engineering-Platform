<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Identity\Application\AssignMembershipRole;
use App\Identity\Domain\Role;
use App\Identity\Infrastructure\EloquentUser;
use App\Identity\Presentation\Requests\AssignRoleRequest;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use DomainException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class AssignRoleController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(
        AssignRoleRequest $request,
        string $userId,
        AssignMembershipRole $assignMembershipRole,
        TenantContext $tenantContext,
    ): JsonResponse {
        $actingUser = $request->user();

        if (! $actingUser instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $assignMembershipRole->handle(
                tenantId: $tenantContext->current(),
                actingUserId: $actingUser->id,
                targetUserId: $userId,
                newRole: Role::from($this->stringField($request, 'role')),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['message' => 'Role updated.']);
    }
}
