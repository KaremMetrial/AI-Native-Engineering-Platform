<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

interface ConstraintRepository
{
    public function save(Constraint $constraint): void;

    public function findById(string $id): ?Constraint;
}
