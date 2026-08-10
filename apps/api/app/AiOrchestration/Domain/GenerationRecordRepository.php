<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

interface GenerationRecordRepository
{
    public function save(GenerationRecord $record): void;

    public function findById(string $id): ?GenerationRecord;

    /**
     * @return list<GenerationRecord>
     */
    public function findAll(): array;
}
