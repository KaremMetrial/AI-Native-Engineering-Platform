<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\RecordResponse;
use App\Discovery\Presentation\Requests\RecordResponseRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class RecordResponseController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(RecordResponseRequest $request, string $questionId, RecordResponse $recordResponse, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $response = $recordResponse->handle(
                tenantId: $tenantContext->current(),
                questionId: $questionId,
                content: $this->stringField($request, 'content'),
                respondedBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'id' => $response->id,
            'question_id' => $response->questionId,
            'content' => $response->content,
        ], 201);
    }
}
