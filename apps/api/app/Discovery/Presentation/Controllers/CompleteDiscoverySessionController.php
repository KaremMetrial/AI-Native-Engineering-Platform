<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\CompleteDiscoverySession;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class CompleteDiscoverySessionController extends Controller
{
    public function __invoke(string $sessionId, CompleteDiscoverySession $completeDiscoverySession): JsonResponse
    {
        try {
            $session = $completeDiscoverySession->handle($sessionId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $session->id,
            'status' => $session->status()->value,
        ]);
    }
}
