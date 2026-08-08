<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\CreateProject;
use App\Graph\Presentation\Requests\CreateProjectRequest;
use App\Http\Controllers\Controller;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;

final class CreateProjectController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(CreateProjectRequest $request, CreateProject $createProject, TenantContext $tenantContext): JsonResponse
    {
        $project = $createProject->handle(
            tenantId: $tenantContext->current(),
            name: $this->stringField($request, 'name'),
        );

        return response()->json([
            'id' => $project->id,
            'name' => $project->name(),
            'status' => $project->status()->value,
        ], 201);
    }
}
