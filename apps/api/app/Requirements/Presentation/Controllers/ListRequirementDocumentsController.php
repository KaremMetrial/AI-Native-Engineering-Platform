<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Requirements\Application\ListRequirementDocuments;
use App\Requirements\Domain\RequirementDocument;
use Illuminate\Http\JsonResponse;

final class ListRequirementDocumentsController extends Controller
{
    public function __invoke(ListRequirementDocuments $listDocuments): JsonResponse
    {
        $documents = array_map(
            fn (RequirementDocument $document): array => [
                'id' => $document->id,
                'project_id' => $document->projectId,
                'type' => $document->type->value,
                'title' => $document->title,
                'status' => $document->status()->value,
            ],
            $listDocuments->handle(),
        );

        return response()->json(['documents' => $documents]);
    }
}
