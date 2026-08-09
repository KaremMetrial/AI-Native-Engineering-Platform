<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\ListArtifactVersionLinks;
use App\Graph\Domain\ArtifactLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ListArtifactVersionLinksController extends Controller
{
    public function __invoke(string $versionId, ListArtifactVersionLinks $listLinks): JsonResponse
    {
        try {
            $links = $listLinks->handle($versionId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $toArray = fn (ArtifactLink $link): array => [
            'id' => $link->id,
            'from_version_id' => $link->fromVersionId,
            'to_version_id' => $link->toVersionId,
            'link_type' => $link->linkType->value,
            'created_by' => $link->createdBy,
        ];

        return response()->json([
            'outgoing' => array_map($toArray, $links['outgoing']),
            'incoming' => array_map($toArray, $links['incoming']),
        ]);
    }
}
