<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\FindProject;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetProjectController extends Controller
{
    public function __invoke(string $projectId, FindProject $findProject): JsonResponse
    {
        $project = $findProject->handle($projectId);

        if ($project === null) {
            return response()->json(['message' => 'Project not found in this tenant.'], 404);
        }

        return response()->json([
            'id' => $project->id,
            'name' => $project->name(),
            'status' => $project->status()->value,
        ]);
    }
}
