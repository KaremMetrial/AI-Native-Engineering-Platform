<?php

declare(strict_types=1);

namespace App\AiOrchestration\Presentation\Controllers;

use App\AiOrchestration\Application\RequestGeneration;
use App\AiOrchestration\Domain\CapabilityRequirement;
use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;
use App\AiOrchestration\Presentation\Requests\RequestGenerationRequest;
use App\Http\Controllers\Controller;
use App\Identity\Infrastructure\EloquentUser;
use App\Shared\ReadsValidatedStrings;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class RequestGenerationController extends Controller
{
    use ReadsValidatedStrings;

    public function __invoke(RequestGenerationRequest $request, RequestGeneration $requestGeneration, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof EloquentUser) {
            throw new RuntimeException('Expected an authenticated EloquentUser.');
        }

        $requirement = new CapabilityRequirement(
            structuredOutput: StructuredOutputCapability::from($this->stringField($request, 'structured_output')),
            toolUse: ToolUseCapability::from($this->stringField($request, 'tool_use')),
            streaming: StreamingCapability::from($this->stringField($request, 'streaming')),
            minContextWindow: $this->intField($request, 'min_context_window'),
            requiresVision: (bool) $request->validated('requires_vision'),
            requiresDeterministicSeed: (bool) $request->validated('requires_deterministic_seed'),
        );

        $record = $requestGeneration->handle(
            tenantId: $tenantContext->current(),
            workflowName: $this->stringField($request, 'workflow_name'),
            requirement: $requirement,
            requestedBy: $user->id,
        );

        return response()->json([
            'id' => $record->id,
            'status' => $record->status()->value,
        ], 202);
    }

    private function intField(RequestGenerationRequest $request, string $key): int
    {
        $value = $request->validated($key);

        if (! is_int($value)) {
            throw new RuntimeException("Expected field [{$key}] to be an integer.");
        }

        return $value;
    }
}
