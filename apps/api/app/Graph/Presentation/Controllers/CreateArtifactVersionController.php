<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\CreateArtifactVersion;
use App\Graph\Presentation\Requests\CreateArtifactVersionRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class CreateArtifactVersionController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(CreateArtifactVersionRequest $request, string $artifactId, CreateArtifactVersion $createArtifactVersion): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $version = $createArtifactVersion->handle(
                artifactId: $artifactId,
                content: $this->stringField($request, 'content'),
                createdBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'id' => $version->id,
            'artifact_id' => $version->artifactId,
            'version_number' => $version->versionNumber,
        ], 201);
    }
}
