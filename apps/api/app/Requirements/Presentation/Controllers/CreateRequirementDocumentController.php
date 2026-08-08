<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Requirements\Application\CreateRequirementDocument;
use App\Requirements\Domain\DocumentType;
use App\Requirements\Presentation\Requests\CreateRequirementDocumentRequest;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class CreateRequirementDocumentController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(CreateRequirementDocumentRequest $request, CreateRequirementDocument $createRequirementDocument, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        try {
            $document = $createRequirementDocument->handle(
                tenantId: $tenantContext->current(),
                projectId: $this->stringField($request, 'project_id'),
                type: DocumentType::from($this->stringField($request, 'type')),
                title: $this->stringField($request, 'title'),
                createdBy: $user->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $document->id,
            'project_id' => $document->projectId,
            'type' => $document->type->value,
            'title' => $document->title,
            'status' => $document->status()->value,
        ], 201);
    }
}
