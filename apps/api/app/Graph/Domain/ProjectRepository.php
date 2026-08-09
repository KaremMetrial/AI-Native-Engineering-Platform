<?php

declare(strict_types=1);

namespace App\Graph\Domain;

interface ProjectRepository
{
    public function save(Project $project): void;

    public function findById(string $id): ?Project;

    /**
     * Every project visible to the acting tenant -- tenant scoping comes
     * from RLS (bound via TenantContext), the same as findById, not an
     * explicit where clause here.
     *
     * @return list<Project>
     */
    public function findAll(): array;
}
