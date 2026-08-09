<?php

declare(strict_types=1);

namespace App\Requirements\Application;

use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Domain\RequirementDocumentRepository;

final class FindRequirementDocument
{
    public function __construct(
        private readonly RequirementDocumentRepository $documents,
    ) {}

    public function handle(string $documentId): ?RequirementDocument
    {
        return $this->documents->findById($documentId);
    }
}
