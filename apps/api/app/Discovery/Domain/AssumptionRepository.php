<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

interface AssumptionRepository
{
    public function save(Assumption $assumption): void;

    public function findById(string $id): ?Assumption;
}
