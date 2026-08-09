<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\ListProjects;
use App\Graph\Domain\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListProjectsController extends Controller
{
    public function __invoke(ListProjects $listProjects): JsonResponse
    {
        $projects = array_map(
            fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name(),
                'status' => $project->status()->value,
            ],
            $listProjects->handle(),
        );

        return response()->json(['projects' => $projects]);
    }
}
