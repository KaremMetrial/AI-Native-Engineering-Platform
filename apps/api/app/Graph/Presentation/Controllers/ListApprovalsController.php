<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Controllers;

use App\Graph\Application\ListApprovalsForVersion;
use App\Graph\Domain\Approval;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ListApprovalsController extends Controller
{
    public function __invoke(string $versionId, ListApprovalsForVersion $listApprovals): JsonResponse
    {
        try {
            $approvals = $listApprovals->handle($versionId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'approvals' => array_map(
                fn (Approval $approval): array => [
                    'id' => $approval->id,
                    'artifact_version_id' => $approval->artifactVersionId,
                    'approved_by' => $approval->approvedBy,
                    'decision' => $approval->decision->value,
                    'comment' => $approval->comment,
                    'created_at' => $approval->createdAt->format(DATE_ATOM),
                ],
                $approvals,
            ),
        ]);
    }
}
