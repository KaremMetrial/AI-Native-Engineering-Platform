<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Requirements\Application\FindRequirementDocument;
use Illuminate\Http\JsonResponse;

final class GetRequirementDocumentController extends Controller
{
    public function __invoke(string $documentId, FindRequirementDocument $findDocument): JsonResponse
    {
        $document = $findDocument->handle($documentId);

        if ($document === null) {
            return response()->json(['message' => 'Requirement document not found in this tenant.'], 404);
        }

        return response()->json([
            'id' => $document->id,
            'project_id' => $document->projectId,
            'type' => $document->type->value,
            'title' => $document->title,
            'status' => $document->status()->value,
        ]);
    }
}
