<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

interface RequirementDocumentRepository
{
    public function save(RequirementDocument $document): void;

    public function findById(string $id): ?RequirementDocument;

    /**
     * Every document visible to the acting tenant -- tenant scoping comes
     * from RLS (bound via TenantContext), the same as findById, not an
     * explicit where clause here.
     *
     * @return list<RequirementDocument>
     */
    public function findAll(): array;
}
