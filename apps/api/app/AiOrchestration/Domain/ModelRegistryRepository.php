<?php

declare(strict_types=1);

namespace App\AiOrchestration\Domain;

interface ModelRegistryRepository
{
    public function save(ModelRegistryEntry $entry): void;

    public function findById(string $id): ?ModelRegistryEntry;

    /**
     * @return list<ModelRegistryEntry>
     */
    public function findByStatus(ModelStatus $status): array;
}
