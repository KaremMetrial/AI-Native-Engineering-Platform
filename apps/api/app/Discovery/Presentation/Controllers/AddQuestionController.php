<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\AddQuestion;
use App\Discovery\Presentation\Requests\AddQuestionRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class AddQuestionController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(AddQuestionRequest $request, string $sessionId, AddQuestion $addQuestion, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $question = $addQuestion->handle(
                tenantId: $tenantContext->current(),
                sessionId: $sessionId,
                prompt: $this->stringField($request, 'prompt'),
                createdBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $question->id,
            'session_id' => $question->sessionId,
            'prompt' => $question->prompt,
            'sequence' => $question->sequence,
        ], 201);
    }
}
