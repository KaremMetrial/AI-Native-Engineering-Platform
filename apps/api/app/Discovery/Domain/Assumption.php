<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

use DateTimeImmutable;

/**
 * Separate aggregate, referencing a session by id (see DiscoverySession's
 * docblock). Kept structurally distinct from Constraint despite an
 * identical shape -- they answer different questions ("what are we
 * taking for granted?" vs. "what limits the solution space?") and the
 * entity catalogue (`docs/architecture/data/42-entity-model-and-ownership.md`)
 * lists them separately.
 */
final class Assumption
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $sessionId,
        public readonly string $statement,
        public readonly string $createdBy,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function capture(
        string $id,
        string $tenantId,
        string $sessionId,
        string $statement,
        string $createdBy,
    ): self {
        return new self($id, $tenantId, $sessionId, $statement, $createdBy, new DateTimeImmutable);
    }
}
