<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

use InvalidArgumentException;

/**
 * Value object: no identity or lifecycle of its own, meaningless apart
 * from the Requirement it describes -- same reasoning as Lineage on
 * ArtifactVersion (app/Graph/Domain/Lineage.php).
 */
final class AcceptanceCriterion
{
    public function __construct(
        public readonly string $description,
    ) {
        if (trim($description) === '') {
            throw new InvalidArgumentException('An acceptance criterion cannot be empty.');
        }
    }
}
