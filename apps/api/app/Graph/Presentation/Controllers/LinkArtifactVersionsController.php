<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\LinkArtifactVersions;
use App\Graph\Domain\LinkType;
use App\Graph\Presentation\Requests\LinkArtifactVersionsRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class LinkArtifactVersionsController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(LinkArtifactVersionsRequest $request, LinkArtifactVersions $linkArtifactVersions, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $link = $linkArtifactVersions->handle(
                tenantId: $tenantContext->current(),
                fromVersionId: $this->stringField($request, 'from_version_id'),
                toVersionId: $this->stringField($request, 'to_version_id'),
                linkType: LinkType::from($this->stringField($request, 'link_type')),
                createdBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $link->id,
            'from_version_id' => $link->fromVersionId,
            'to_version_id' => $link->toVersionId,
            'link_type' => $link->linkType->value,
        ], 201);
    }
}
