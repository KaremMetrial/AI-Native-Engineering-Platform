<?php

declare(strict_types=1);

namespace App\Graph\Domain;

/**
 * A closed set, deliberately (D-338, docs/architecture/design/32-domain-model-and-ddd.md):
 * "an open vocabulary of link types would make traversal semantics
 * unknowable." Adding a type requires an ADR, because each one changes
 * what impact analysis means.
 */
enum LinkType: string
{
    case DerivesFrom = 'derives_from';
    case Satisfies = 'satisfies';
    case Implements = 'implements';
    case Verifies = 'verifies';
    case Supersedes = 'supersedes';
    case References = 'references';
}
