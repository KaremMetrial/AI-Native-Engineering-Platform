<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\ListResponsesForQuestion;
use App\Discovery\Domain\Response;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ListResponsesController extends Controller
{
    public function __invoke(string $questionId, ListResponsesForQuestion $listResponses): JsonResponse
    {
        try {
            $responses = $listResponses->handle($questionId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'responses' => array_map(
                fn (Response $response): array => [
                    'id' => $response->id,
                    'question_id' => $response->questionId,
                    'content' => $response->content,
                    'responded_by' => $response->respondedBy,
                ],
                $responses,
            ),
        ]);
    }
}
