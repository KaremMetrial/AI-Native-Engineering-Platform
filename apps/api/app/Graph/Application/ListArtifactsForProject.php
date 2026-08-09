<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\Artifact;
use App\Graph\Domain\ArtifactRepository;
use App\Graph\Domain\ProjectRepository;
use RuntimeException;

final class ListArtifactsForProject
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ArtifactRepository $artifacts,
    ) {}

    /**
     * @return list<Artifact>
     */
    public function handle(string $projectId): array
    {
        if ($this->projects->findById($projectId) === null) {
            throw new RuntimeException('Project not found in this tenant.');
        }

        return $this->artifacts->findAllForProject($projectId);
    }
}
