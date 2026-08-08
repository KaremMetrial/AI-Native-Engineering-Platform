<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\Project;
use App\Graph\Domain\ProjectRepository;

/**
 * The read-side seam other modules use to validate a project reference
 * (`docs/delivery/11-repository-and-folder-strategy.md`: "a module imports
 * another module only via its Application layer"). Graph is importable by
 * all, so this is how e.g. Discovery confirms a project exists without
 * reaching into Graph's Domain or Infrastructure internals.
 */
final class FindProject
{
    public function __construct(
        private readonly ProjectRepository $projects,
    ) {}

    public function handle(string $projectId): ?Project
    {
        return $this->projects->findById($projectId);
    }
}
