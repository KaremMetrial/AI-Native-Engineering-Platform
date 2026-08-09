<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\FindArtifact;
use App\Graph\Domain\ArtifactVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetArtifactController extends Controller
{
    public function __invoke(string $artifactId, FindArtifact $findArtifact): JsonResponse
    {
        $artifact = $findArtifact->handle($artifactId);

        if ($artifact === null) {
            return response()->json(['message' => 'Artifact not found in this tenant.'], 404);
        }

        return response()->json([
            'id' => $artifact->id,
            'project_id' => $artifact->projectId,
            'type' => $artifact->type,
            'status' => $artifact->status()->value,
            'current_version_id' => $artifact->currentVersionId(),
            'versions' => array_map(
                fn (ArtifactVersion $version): array => [
                    'id' => $version->id,
                    'version_number' => $version->versionNumber,
                    'content' => $version->content,
                    'lineage' => [
                        'model' => $version->lineage->model,
                        'prompt_version' => $version->lineage->promptVersion,
                        'input_version_ids' => $version->lineage->inputVersionIds,
                        'tokens' => $version->lineage->tokens,
                        'cost' => $version->lineage->cost,
                    ],
                    'created_by' => $version->createdBy,
                    'created_at' => $version->createdAt->format(DATE_ATOM),
                ],
                $artifact->versions(),
            ),
        ]);
    }
}
