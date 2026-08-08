<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Separate aggregate from DiscoverySession (see its docblock). `sequence`
 * is the question's position within its session, assigned by the
 * Application layer from the current count -- not a domain invariant this
 * entity enforces itself.
 */
final class Question
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $sessionId,
        public readonly string $prompt,
        public readonly int $sequence,
        public readonly string $createdBy,
        public readonly DateTimeImmutable $createdAt,
    ) {
        if ($sequence < 1) {
            throw new DomainException('A question sequence must be a positive integer.');
        }
    }

    public static function ask(
        string $id,
        string $tenantId,
        string $sessionId,
        string $prompt,
        int $sequence,
        string $createdBy,
    ): self {
        return new self($id, $tenantId, $sessionId, $prompt, $sequence, $createdBy, new DateTimeImmutable);
    }
}
