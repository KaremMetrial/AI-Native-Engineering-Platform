<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\CaptureConstraint;
use App\Discovery\Presentation\Requests\CaptureConstraintRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class CaptureConstraintController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(CaptureConstraintRequest $request, string $sessionId, CaptureConstraint $captureConstraint, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $constraint = $captureConstraint->handle(
                tenantId: $tenantContext->current(),
                sessionId: $sessionId,
                statement: $this->stringField($request, 'statement'),
                createdBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $constraint->id,
            'session_id' => $constraint->sessionId,
            'statement' => $constraint->statement,
        ], 201);
    }
}
