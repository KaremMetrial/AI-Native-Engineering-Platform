<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Controllers;

use App\Discovery\Application\ListConstraintsForSession;
use App\Discovery\Domain\Constraint;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ListConstraintsController extends Controller
{
    public function __invoke(string $sessionId, ListConstraintsForSession $listConstraints): JsonResponse
    {
        try {
            $constraints = $listConstraints->handle($sessionId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'constraints' => array_map(
                fn (Constraint $constraint): array => [
                    'id' => $constraint->id,
                    'session_id' => $constraint->sessionId,
                    'statement' => $constraint->statement,
                ],
                $constraints,
            ),
        ]);
    }
}
