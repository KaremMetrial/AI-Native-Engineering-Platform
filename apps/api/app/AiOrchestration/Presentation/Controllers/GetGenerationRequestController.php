<?php

declare(strict_types=1);

namespace App\AiOrchestration\Presentation\Controllers;

use App\AiOrchestration\Domain\GenerationRecordRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetGenerationRequestController extends Controller
{
    public function __invoke(string $generationRequestId, GenerationRecordRepository $records): JsonResponse
    {
        $record = $records->findById($generationRequestId);

        if ($record === null) {
            return response()->json(['message' => 'Generation request not found in this tenant.'], 404);
        }

        return response()->json([
            'id' => $record->id,
            'workflow_name' => $record->workflowName,
            'status' => $record->status()->value,
            'selected_model_id' => $record->selectedModelId(),
            'failure_reason' => $record->failureReason(),
        ]);
    }
}
