<?php

declare(strict_types=1);

namespace App\AiOrchestration\Presentation\Controllers;

use App\AiOrchestration\Application\ListGenerationRequests;
use App\AiOrchestration\Domain\GenerationRecord;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListGenerationRequestsController extends Controller
{
    public function __invoke(ListGenerationRequests $listGenerationRequests): JsonResponse
    {
        $requests = array_map(
            fn (GenerationRecord $record): array => [
                'id' => $record->id,
                'workflow_name' => $record->workflowName,
                'status' => $record->status()->value,
                'selected_model_id' => $record->selectedModelId(),
                'failure_reason' => $record->failureReason(),
            ],
            $listGenerationRequests->handle(),
        );

        return response()->json(['generation_requests' => $requests]);
    }
}
