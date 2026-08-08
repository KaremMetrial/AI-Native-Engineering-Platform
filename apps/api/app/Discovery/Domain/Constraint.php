<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

use DateTimeImmutable;

/**
 * Separate aggregate, referencing a session by id (see DiscoverySession's
 * docblock). See Assumption's docblock for why this is a distinct type
 * despite the identical shape.
 */
final class Constraint
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
