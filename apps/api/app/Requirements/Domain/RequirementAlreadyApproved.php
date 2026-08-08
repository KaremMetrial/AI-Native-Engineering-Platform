<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

use DomainException;

/**
 * Named for the violated rule, not the failure
 * (docs/foundation/25-naming-standards.md's own example: "RequirementAlreadyApproved
 * tells a reader what went wrong. InvalidStateException tells them
 * nothing and forces them into the stack trace.").
 */
final class RequirementAlreadyApproved extends DomainException
{
    public static function forRequirement(string $requirementId): self
    {
        return new self("Requirement [{$requirementId}] is already approved.");
    }
}
