<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\ListDiscoverySessions;
use App\Discovery\Domain\DiscoverySession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListDiscoverySessionsController extends Controller
{
    public function __invoke(ListDiscoverySessions $listSessions): JsonResponse
    {
        $sessions = array_map(
            fn (DiscoverySession $session): array => [
                'id' => $session->id,
                'project_id' => $session->projectId,
                'title' => $session->title,
                'status' => $session->status()->value,
                'completed_at' => $session->completedAt()?->format(DATE_ATOM),
            ],
            $listSessions->handle(),
        );

        return response()->json(['sessions' => $sessions]);
    }
}
