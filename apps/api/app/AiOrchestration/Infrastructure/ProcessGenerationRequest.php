<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use App\AiOrchestration\Application\SelectCandidateModels;
use App\AiOrchestration\Domain\GenerationRecordRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * The queue-side half of the P-9 boundary
 * (docs/architecture/04-non-functional-requirements.md): RequestGeneration
 * (Application) returns immediately after queuing this; this job does the
 * actual work of selecting a candidate model, off the request path. A
 * framework-level entry point invoking Application logic, the same role
 * a Controller plays for HTTP -- placed in Infrastructure because
 * dispatch-via-queue is a framework mechanism, not a use case itself.
 */
final class ProcessGenerationRequest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $generationRecordId,
    ) {}

    public function handle(GenerationRecordRepository $records, SelectCandidateModels $selectCandidateModels): void
    {
        $record = $records->findById($this->generationRecordId);

        if ($record === null) {
            throw new RuntimeException("Generation record [{$this->generationRecordId}] not found.");
        }

        $candidates = $selectCandidateModels->handle($record->tenantId, $record->capabilityRequirement);

        if ($candidates === []) {
            $record->fail('No active model satisfies the requested capabilities and tenant provider policy.');
        } else {
            $record->selectModel($candidates[0]->modelId);
        }

        $records->save($record);
    }
}
