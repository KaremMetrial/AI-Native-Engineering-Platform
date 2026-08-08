<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

interface RequirementDocumentRepository
{
    public function save(RequirementDocument $document): void;

    public function findById(string $id): ?RequirementDocument;
}
