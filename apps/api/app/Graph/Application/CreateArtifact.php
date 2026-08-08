<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\Artifact;
use App\Graph\Domain\ArtifactRepository;
use App\Graph\Domain\ProjectRepository;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates an Artifact with its first version, atomically (D-333: one
 * aggregate per transaction -- Artifact::create() builds both in memory,
 * ArtifactRepository::save() persists both in one transaction).
 */
final class CreateArtifact
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ArtifactRepository $artifacts,
    ) {}

    public function handle(
        string $tenantId,
        string $projectId,
        string $type,
        string $content,
        string $createdBy,
    ): Artifact {
        if ($this->projects->findById($projectId) === null) {
            throw new RuntimeException('Project not found in this tenant.');
        }

        $artifact = Artifact::create(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            projectId: $projectId,
            type: $type,
            firstVersionId: (string) Str::uuid(),
            content: $content,
            createdBy: $createdBy,
        );

        $this->artifacts->save($artifact);

        return $artifact;
    }
}
