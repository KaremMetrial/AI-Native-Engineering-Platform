<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

use DomainException;

/**
 * Named for the violated rule (docs/foundation/25-naming-standards.md):
 * "cannot be approved while any requirement is incomplete"
 * (docs/architecture/design/32-domain-model-and-ddd.md). Thrown from the
 * Application layer (ApproveRequirementDocument), not RequirementDocument
 * itself, because the check is cross-aggregate -- see
 * RequirementDocument's docblock.
 */
final class IncompleteRequirementsExist extends DomainException
{
    public static function forDocument(string $documentId): self
    {
        return new self("Requirement document [{$documentId}] cannot be approved while any requirement is incomplete.");
    }
}
