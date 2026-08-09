<?php

declare(strict_types=1);

namespace App\Requirements\Application;

use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementDocumentRepository;
use App\Requirements\Domain\RequirementRepository;
use RuntimeException;

final class ListRequirementsForDocument
{
    public function __construct(
        private readonly RequirementDocumentRepository $documents,
        private readonly RequirementRepository $requirements,
    ) {}

    /**
     * @return list<Requirement>
     */
    public function handle(string $documentId): array
    {
        if ($this->documents->findById($documentId) === null) {
            throw new RuntimeException('Requirement document not found in this tenant.');
        }

        return $this->requirements->findAllByDocument($documentId);
    }
}
