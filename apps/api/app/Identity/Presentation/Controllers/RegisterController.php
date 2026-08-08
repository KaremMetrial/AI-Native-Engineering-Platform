<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Identity\Application\RegisterTenant;
use App\Identity\Application\RegisterTenantCommand;
use App\Identity\Presentation\Requests\RegisterRequest;
use App\Shared\ReadsValidatedStrings;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class RegisterController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(RegisterRequest $request, RegisterTenant $registerTenant): JsonResponse
    {
        try {
            $result = $registerTenant->handle(new RegisterTenantCommand(
                tenantName: $this->stringField($request, 'tenant_name'),
                ownerName: $this->stringField($request, 'name'),
                ownerEmail: $this->stringField($request, 'email'),
                ownerPassword: $this->stringField($request, 'password'),
            ));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'tenant' => ['id' => $result->tenant->id, 'name' => $result->tenant->name()],
            'user' => ['id' => $result->owner->id, 'name' => $result->owner->name(), 'email' => $result->owner->email()],
            'role' => $result->membership->role()->value,
            'token' => $result->token,
        ], 201);
    }
}
