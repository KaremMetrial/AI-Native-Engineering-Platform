<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\TraverseImpact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class TraverseImpactController extends Controller
{
    public function __invoke(string $versionId, TraverseImpact $traverseImpact): JsonResponse
    {
        try {
            $impactedVersionIds = $traverseImpact->handle($versionId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['impacted_version_ids' => $impactedVersionIds]);
    }
}
