<?php

declare(strict_types=1);

namespace Tests\Integration\AiOrchestration;

use App\AiOrchestration\Infrastructure\ProcessGenerationRequest;
use App\AiOrchestration\Infrastructure\QueuedGenerationDispatcher;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QueuedGenerationDispatcherTest extends TestCase
{
    public function test_dispatch_queues_a_process_generation_request_job(): void
    {
        Bus::fake();

        (new QueuedGenerationDispatcher)->dispatch('record-1');

        Bus::assertDispatched(
            ProcessGenerationRequest::class,
            fn (ProcessGenerationRequest $job): bool => $job->generationRecordId === 'record-1',
        );
    }
}
