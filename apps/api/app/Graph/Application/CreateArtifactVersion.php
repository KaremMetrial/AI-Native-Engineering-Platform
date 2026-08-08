<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\ArtifactRepository;
use App\Graph\Domain\ArtifactVersion;
use App\Graph\Domain\Lineage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Appends a new version to an existing Artifact. There is no update path
 * (D-336) -- editing means creating a version, never mutating one.
 */
final class CreateArtifactVersion
{
    public function __construct(
        private readonly ArtifactRepository $artifacts,
    ) {}

    public function handle(string $artifactId, string $content, string $createdBy): ArtifactVersion
    {
        $artifact = $this->artifacts->findById($artifactId);

        if ($artifact === null) {
            throw new RuntimeException('Artifact not found in this tenant.');
        }

        $version = $artifact->recordNewVersion((string) Str::uuid(), $content, Lineage::human(), $createdBy);

        $this->artifacts->save($artifact);

        return $version;
    }
}
