<?php

declare(strict_types=1);

namespace App\Graph\Domain;

interface ProjectRepository
{
    public function save(Project $project): void;

    public function findById(string $id): ?Project;
}
