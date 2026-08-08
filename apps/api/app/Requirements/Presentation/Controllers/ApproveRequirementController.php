<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Requirements\Application\ApproveRequirement;
use DomainException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ApproveRequirementController extends Controller
{
    public function __invoke(string $requirementId, ApproveRequirement $approveRequirement): JsonResponse
    {
        try {
            $requirement = $approveRequirement->handle($requirementId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $requirement->id,
            'status' => $requirement->status()->value,
        ]);
    }
}
