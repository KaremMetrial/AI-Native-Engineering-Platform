<?php

declare(strict_types=1);

namespace App\Requirements\Application;

use App\Requirements\Domain\IncompleteRequirementsExist;
use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Domain\RequirementDocumentRepository;
use App\Requirements\Domain\RequirementDocumentStatus;
use App\Requirements\Domain\RequirementRepository;
use RuntimeException;

/**
 * Enforces "cannot be approved while any requirement is incomplete"
 * (docs/architecture/design/32-domain-model-and-ddd.md) -- a cross-aggregate
 * invariant that RequirementDocument itself cannot enforce, since
 * Requirement is a separate aggregate (see RequirementDocument's docblock).
 */
final class ApproveRequirementDocument
{
    public function __construct(
        private readonly RequirementDocumentRepository $documents,
        private readonly RequirementRepository $requirements,
    ) {}

    public function handle(string $documentId): RequirementDocument
    {
        $document = $this->documents->findById($documentId);

        if ($document === null) {
            throw new RuntimeException('Requirement document not found in this tenant.');
        }

        if ($document->status() !== RequirementDocumentStatus::Approved) {
            $total = $this->requirements->countByDocument($documentId);
            $approved = $this->requirements->countApprovedByDocument($documentId);

            if ($total === 0 || $approved !== $total) {
                throw IncompleteRequirementsExist::forDocument($documentId);
            }
        }

        $document->approve();

        $this->documents->save($document);

        return $document;
    }
}
