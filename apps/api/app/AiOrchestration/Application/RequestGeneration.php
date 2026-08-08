<?php

declare(strict_types=1);

namespace App\AiOrchestration\Application;

use App\AiOrchestration\Domain\CapabilityRequirement;
use App\AiOrchestration\Domain\GenerationDispatcher;
use App\AiOrchestration\Domain\GenerationRecord;
use App\AiOrchestration\Domain\GenerationRecordRepository;
use Illuminate\Support\Str;

/**
 * The P-9 sync boundary (docs/architecture/04-non-functional-requirements.md):
 * creates the record and queues the job, then returns immediately. No AI
 * inference happens on this call stack.
 */
final class RequestGeneration
{
    public function __construct(
        private readonly GenerationRecordRepository $records,
        private readonly GenerationDispatcher $dispatcher,
    ) {}

    public function handle(string $tenantId, string $workflowName, CapabilityRequirement $requirement, string $requestedBy): GenerationRecord
    {
        $record = GenerationRecord::request(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            workflowName: $workflowName,
            capabilityRequirement: $requirement,
            requestedBy: $requestedBy,
        );

        $this->records->save($record);

        $this->dispatcher->dispatch($record->id);

        return $record;
    }
}
