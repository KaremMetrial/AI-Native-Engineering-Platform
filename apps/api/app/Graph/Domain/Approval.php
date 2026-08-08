<?php

declare(strict_types=1);

namespace App\Graph\Domain;

use DateTimeImmutable;

/**
 * Separate aggregate (D-335). Binds to a *version*, never an artifact
 * (D-278): "a floating approval would mean a contract was approved
 * against text nobody read."
 */
final class Approval
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $artifactVersionId,
        public readonly string $approvedBy,
        public readonly ApprovalDecision $decision,
        public readonly ?string $comment,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function record(
        string $id,
        string $tenantId,
        string $artifactVersionId,
        string $approvedBy,
        ApprovalDecision $decision,
        ?string $comment,
    ): self {
        return new self($id, $tenantId, $artifactVersionId, $approvedBy, $decision, $comment, new DateTimeImmutable);
    }
}
