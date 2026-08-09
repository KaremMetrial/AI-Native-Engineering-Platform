<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\FindDiscoverySession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetDiscoverySessionController extends Controller
{
    public function __invoke(string $sessionId, FindDiscoverySession $findSession): JsonResponse
    {
        $session = $findSession->handle($sessionId);

        if ($session === null) {
            return response()->json(['message' => 'Discovery session not found in this tenant.'], 404);
        }

        return response()->json([
            'id' => $session->id,
            'project_id' => $session->projectId,
            'title' => $session->title,
            'status' => $session->status()->value,
            'completed_at' => $session->completedAt()?->format(DATE_ATOM),
        ]);
    }
}
