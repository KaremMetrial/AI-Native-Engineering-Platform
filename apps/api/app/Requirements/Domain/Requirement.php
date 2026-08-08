<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

use DateTimeImmutable;

/**
 * Separate aggregate from RequirementDocument (see its docblock).
 */
final class Requirement
{
    /**
     * @param  list<AcceptanceCriterion>  $acceptanceCriteria
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $documentId,
        public readonly string $text,
        private readonly array $acceptanceCriteria,
        private RequirementStatus $status,
        public readonly string $createdBy,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param  list<AcceptanceCriterion>  $acceptanceCriteria
     */
    public static function draft(
        string $id,
        string $tenantId,
        string $documentId,
        string $text,
        array $acceptanceCriteria,
        string $createdBy,
    ): self {
        return new self($id, $tenantId, $documentId, $text, $acceptanceCriteria, RequirementStatus::Draft, $createdBy, new DateTimeImmutable);
    }

    public function approve(): void
    {
        if ($this->status === RequirementStatus::Approved) {
            throw RequirementAlreadyApproved::forRequirement($this->id);
        }

        $this->status = RequirementStatus::Approved;
    }

    public function status(): RequirementStatus
    {
        return $this->status;
    }

    /**
     * @return list<AcceptanceCriterion>
     */
    public function acceptanceCriteria(): array
    {
        return $this->acceptanceCriteria;
    }
}
