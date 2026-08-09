<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Requirements\Application\ListRequirementsForDocument;
use App\Requirements\Domain\AcceptanceCriterion;
use App\Requirements\Domain\Requirement;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ListRequirementsController extends Controller
{
    public function __invoke(string $documentId, ListRequirementsForDocument $listRequirements): JsonResponse
    {
        try {
            $requirements = $listRequirements->handle($documentId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'requirements' => array_map(
                fn (Requirement $requirement): array => [
                    'id' => $requirement->id,
                    'document_id' => $requirement->documentId,
                    'text' => $requirement->text,
                    'acceptance_criteria' => array_map(
                        fn (AcceptanceCriterion $criterion): string => $criterion->description,
                        $requirement->acceptanceCriteria(),
                    ),
                    'status' => $requirement->status()->value,
                ],
                $requirements,
            ),
        ]);
    }
}
