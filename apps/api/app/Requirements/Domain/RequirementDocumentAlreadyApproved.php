<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

use DomainException;

/**
 * Named for the violated rule (docs/foundation/25-naming-standards.md) --
 * see RequirementAlreadyApproved's docblock for the same reasoning applied
 * at the document level.
 */
final class RequirementDocumentAlreadyApproved extends DomainException
{
    public static function forDocument(string $documentId): self
    {
        return new self("Requirement document [{$documentId}] is already approved.");
    }
}
