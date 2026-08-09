<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\ListArtifactsForProject;
use App\Graph\Domain\Artifact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ListProjectArtifactsController extends Controller
{
    public function __invoke(string $projectId, ListArtifactsForProject $listArtifacts): JsonResponse
    {
        try {
            $artifacts = $listArtifacts->handle($projectId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'artifacts' => array_map(
                fn (Artifact $artifact): array => [
                    'id' => $artifact->id,
                    'project_id' => $artifact->projectId,
                    'type' => $artifact->type,
                    'status' => $artifact->status()->value,
                    'current_version_id' => $artifact->currentVersionId(),
                    'version_count' => count($artifact->versions()),
                ],
                $artifacts,
            ),
        ]);
    }
}
