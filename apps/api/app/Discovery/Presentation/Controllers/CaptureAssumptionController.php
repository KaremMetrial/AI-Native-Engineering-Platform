<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\CaptureAssumption;
use App\Discovery\Presentation\Requests\CaptureAssumptionRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class CaptureAssumptionController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(CaptureAssumptionRequest $request, string $sessionId, CaptureAssumption $captureAssumption, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $assumption = $captureAssumption->handle(
                tenantId: $tenantContext->current(),
                sessionId: $sessionId,
                statement: $this->stringField($request, 'statement'),
                createdBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $assumption->id,
            'session_id' => $assumption->sessionId,
            'statement' => $assumption->statement,
        ], 201);
    }
}
