<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\ApproveArtifactVersion;
use App\Graph\Domain\ApprovalDecision;
use App\Graph\Presentation\Requests\ApproveArtifactVersionRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ApproveArtifactVersionController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(
        ApproveArtifactVersionRequest $request,
        string $versionId,
        ApproveArtifactVersion $approveArtifactVersion,
        TenantContext $tenantContext,
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        $comment = $request->validated('comment');

        try {
            $approval = $approveArtifactVersion->handle(
                tenantId: $tenantContext->current(),
                artifactVersionId: $versionId,
                approvedBy: $user->id,
                decision: ApprovalDecision::from($this->stringField($request, 'decision')),
                comment: is_string($comment) ? $comment : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'id' => $approval->id,
            'artifact_version_id' => $approval->artifactVersionId,
            'decision' => $approval->decision->value,
        ], 201);
    }
}
