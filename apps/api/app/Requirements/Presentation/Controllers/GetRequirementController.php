<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Requirements\Application\FindRequirement;
use App\Requirements\Domain\AcceptanceCriterion;
use Illuminate\Http\JsonResponse;

final class GetRequirementController extends Controller
{
    public function __invoke(string $requirementId, FindRequirement $findRequirement): JsonResponse
    {
        $requirement = $findRequirement->handle($requirementId);

        if ($requirement === null) {
            return response()->json(['message' => 'Requirement not found in this tenant.'], 404);
        }

        return response()->json([
            'id' => $requirement->id,
            'document_id' => $requirement->documentId,
            'text' => $requirement->text,
            'acceptance_criteria' => array_map(
                fn (AcceptanceCriterion $criterion): string => $criterion->description,
                $requirement->acceptanceCriteria(),
            ),
            'status' => $requirement->status()->value,
        ]);
    }
}
