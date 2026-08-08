<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

use DateTimeImmutable;

/**
 * Aggregate root (docs/architecture/design/32-domain-model-and-ddd.md):
 * "cannot be approved while any requirement is incomplete." `Requirement`
 * is deliberately a separate aggregate, not a child collection here --
 * individual requirements are edited, approved and traced independently,
 * and a document-level aggregate would serialize all editing within a
 * document. The "all requirements approved" check that gates this
 * aggregate's own approve() is therefore a cross-aggregate invariant
 * enforced by the Application layer (ApproveRequirementDocument), not by
 * this class -- the same pattern Discovery uses for cross-aggregate
 * session-state checks.
 *
 * Not yet linked to a Delivery Graph Artifact -- see
 * app/Requirements/README.md for why that integration is deferred rather
 * than guessed at here.
 */
final class RequirementDocument
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $projectId,
        public readonly DocumentType $type,
        public readonly string $title,
        private RequirementDocumentStatus $status,
        public readonly string $createdBy,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string $id,
        string $tenantId,
        string $projectId,
        DocumentType $type,
        string $title,
        string $createdBy,
    ): self {
        return new self($id, $tenantId, $projectId, $type, $title, RequirementDocumentStatus::Draft, $createdBy, new DateTimeImmutable);
    }

    public function approve(): void
    {
        if ($this->status === RequirementDocumentStatus::Approved) {
            throw RequirementDocumentAlreadyApproved::forDocument($this->id);
        }

        $this->status = RequirementDocumentStatus::Approved;
    }

    public function status(): RequirementDocumentStatus
    {
        return $this->status;
    }
}
