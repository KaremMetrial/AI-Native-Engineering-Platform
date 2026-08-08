<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Identity\Application\AuthenticateUser;
use App\Identity\Presentation\Requests\LoginRequest;
use App\Shared\ReadsValidatedStrings;
use Illuminate\Http\JsonResponse;

final class LoginController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(LoginRequest $request, AuthenticateUser $authenticateUser): JsonResponse
    {
        $result = $authenticateUser->handle(
            $this->stringField($request, 'email'),
            $this->stringField($request, 'password'),
        );

        if (! $result->succeeded || $result->user === null || $result->token === null) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json([
            'user' => ['id' => $result->user->id, 'name' => $result->user->name(), 'email' => $result->user->email()],
            'token' => $result->token,
        ]);
    }
}
