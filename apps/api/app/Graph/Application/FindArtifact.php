<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\Artifact;
use App\Graph\Domain\ArtifactRepository;

final class FindArtifact
{
    public function __construct(
        private readonly ArtifactRepository $artifacts,
    ) {}

    public function handle(string $artifactId): ?Artifact
    {
        return $this->artifacts->findById($artifactId);
    }
}
