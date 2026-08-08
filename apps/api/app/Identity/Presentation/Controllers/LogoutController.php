<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        // currentAccessToken() is never null on a route guarded by
        // auth:sanctum (it's only null when authenticated via the session
        // guard instead of a token, which this route doesn't accept).
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
