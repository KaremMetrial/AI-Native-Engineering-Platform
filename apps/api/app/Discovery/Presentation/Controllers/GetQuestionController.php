<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\FindQuestion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetQuestionController extends Controller
{
    public function __invoke(string $questionId, FindQuestion $findQuestion): JsonResponse
    {
        $question = $findQuestion->handle($questionId);

        if ($question === null) {
            return response()->json(['message' => 'Discovery question not found in this tenant.'], 404);
        }

        return response()->json([
            'id' => $question->id,
            'session_id' => $question->sessionId,
            'prompt' => $question->prompt,
            'sequence' => $question->sequence,
        ]);
    }
}
