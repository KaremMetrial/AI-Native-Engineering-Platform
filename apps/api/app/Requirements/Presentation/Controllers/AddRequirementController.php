<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Requirements\Application\AddRequirement;
use App\Requirements\Presentation\Requests\AddRequirementRequest;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class AddRequirementController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(AddRequirementRequest $request, string $documentId, AddRequirement $addRequirement, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $requirement = $addRequirement->handle(
                tenantId: $tenantContext->current(),
                documentId: $documentId,
                text: $this->stringField($request, 'text'),
                acceptanceCriteria: $this->stringListField($request, 'acceptance_criteria'),
                createdBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $requirement->id,
            'document_id' => $requirement->documentId,
            'text' => $requirement->text,
            'status' => $requirement->status()->value,
        ], 201);
    }

    /**
     * @return list<string>
     */
    private function stringListField(AddRequirementRequest $request, string $key): array
    {
        $value = $request->validated($key);

        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw new RuntimeException("Expected field [{$key}] to be an array.");
        }

        return array_values(array_map(
            static function (mixed $item) use ($key): string {
                if (! is_string($item)) {
                    throw new RuntimeException("Expected each entry of [{$key}] to be a string.");
                }

                return $item;
            },
            $value,
        ));
    }
}
