<?php

declare(strict_types=1);

namespace App\AiOrchestration\Application;

use App\AiOrchestration\Domain\GenerationRecord;
use App\AiOrchestration\Domain\GenerationRecordRepository;

final class ListGenerationRequests
{
    public function __construct(
        private readonly GenerationRecordRepository $records,
    ) {}

    /**
     * @return list<GenerationRecord>
     */
    public function handle(): array
    {
        return $this->records->findAll();
    }
}
