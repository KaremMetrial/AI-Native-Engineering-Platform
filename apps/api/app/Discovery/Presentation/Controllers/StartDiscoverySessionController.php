<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\StartDiscoverySession;
use App\Discovery\Presentation\Requests\StartDiscoverySessionRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class StartDiscoverySessionController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(StartDiscoverySessionRequest $request, StartDiscoverySession $startDiscoverySession, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $session = $startDiscoverySession->handle(
                tenantId: $tenantContext->current(),
                projectId: $this->stringField($request, 'project_id'),
                title: $this->stringField($request, 'title'),
                createdBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $session->id,
            'project_id' => $session->projectId,
            'title' => $session->title,
            'status' => $session->status()->value,
        ], 201);
    }
}
