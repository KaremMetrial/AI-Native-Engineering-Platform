<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\CreateArtifact;
use App\Graph\Presentation\Requests\CreateArtifactRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class CreateArtifactController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(CreateArtifactRequest $request, CreateArtifact $createArtifact, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $artifact = $createArtifact->handle(
                tenantId: $tenantContext->current(),
                projectId: $this->stringField($request, 'project_id'),
                type: $this->stringField($request, 'type'),
                content: $this->stringField($request, 'content'),
                createdBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $artifact->id,
            'type' => $artifact->type,
            'status' => $artifact->status()->value,
            'current_version_id' => $artifact->currentVersionId(),
        ], 201);
    }
}
