<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\ListQuestionsForSession;
use App\Discovery\Domain\Question;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ListQuestionsController extends Controller
{
    public function __invoke(string $sessionId, ListQuestionsForSession $listQuestions): JsonResponse
    {
        try {
            $questions = $listQuestions->handle($sessionId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'questions' => array_map(
                fn (Question $question): array => [
                    'id' => $question->id,
                    'session_id' => $question->sessionId,
                    'prompt' => $question->prompt,
                    'sequence' => $question->sequence,
                ],
                $questions,
            ),
        ]);
    }
}
