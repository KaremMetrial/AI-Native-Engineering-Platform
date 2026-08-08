<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Aggregate root of a discovery engagement (`docs/product/03-core-modules-and-scope.md`,
 * C2). Question, Response, Assumption and Constraint are deliberately
 * separate aggregates that reference a session by id rather than living as
 * children here -- for the same reason Graph splits ArtifactLink and
 * Approval out of Artifact (D-335): concurrent stakeholders answering
 * different questions in the same session must not serialize against each
 * other or against this aggregate.
 */
final class DiscoverySession
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $projectId,
        public readonly string $title,
        private SessionStatus $status,
        public readonly string $createdBy,
        public readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt = null,
    ) {}

    public static function start(
        string $id,
        string $tenantId,
        string $projectId,
        string $title,
        string $createdBy,
    ): self {
        return new self($id, $tenantId, $projectId, $title, SessionStatus::InProgress, $createdBy, new DateTimeImmutable);
    }

    public function complete(): void
    {
        if ($this->status === SessionStatus::Completed) {
            throw new DomainException('Discovery session is already completed.');
        }

        $this->status = SessionStatus::Completed;
        $this->completedAt = new DateTimeImmutable;
    }

    public function status(): SessionStatus
    {
        return $this->status;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }
}
