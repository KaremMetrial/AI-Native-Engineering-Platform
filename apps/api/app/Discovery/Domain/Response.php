<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

use DateTimeImmutable;

/**
 * Separate aggregate, bound to a Question (see DiscoverySession's
 * docblock). Unlike ArtifactVersion, a Response is raw intake, not a
 * governed artifact -- there is no lineage or approval concept at this
 * layer, and multiple responses to one question (e.g. more than one
 * stakeholder answering) are allowed rather than treated as conflicting
 * versions.
 */
final class Response
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $questionId,
        public readonly string $content,
        public readonly string $respondedBy,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function record(
        string $id,
        string $tenantId,
        string $questionId,
        string $content,
        string $respondedBy,
    ): self {
        return new self($id, $tenantId, $questionId, $content, $respondedBy, new DateTimeImmutable);
    }
}
