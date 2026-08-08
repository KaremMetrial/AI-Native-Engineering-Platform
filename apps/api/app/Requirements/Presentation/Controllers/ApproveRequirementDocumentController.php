<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Requirements\Application\ApproveRequirementDocument;
use DomainException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ApproveRequirementDocumentController extends Controller
{
    public function __invoke(string $documentId, ApproveRequirementDocument $approveRequirementDocument): JsonResponse
    {
        try {
            $document = $approveRequirementDocument->handle($documentId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $document->id,
            'status' => $document->status()->value,
        ]);
    }
}
