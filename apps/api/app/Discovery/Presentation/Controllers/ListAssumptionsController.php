<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\ListAssumptionsForSession;
use App\Discovery\Domain\Assumption;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ListAssumptionsController extends Controller
{
    public function __invoke(string $sessionId, ListAssumptionsForSession $listAssumptions): JsonResponse
    {
        try {
            $assumptions = $listAssumptions->handle($sessionId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'assumptions' => array_map(
                fn (Assumption $assumption): array => [
                    'id' => $assumption->id,
                    'session_id' => $assumption->sessionId,
                    'statement' => $assumption->statement,
                ],
                $assumptions,
            ),
        ]);
    }
}
