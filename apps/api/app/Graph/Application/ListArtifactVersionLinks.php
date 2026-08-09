<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\ArtifactLink;
use App\Graph\Domain\ArtifactLinkRepository;
use App\Graph\Domain\ArtifactVersionFinder;
use RuntimeException;

/**
 * A version's one-hop link neighborhood in both directions -- what it
 * links to and what links to it. Deep reverse impact analysis is
 * TraverseImpact's job; this is the shallow view a version detail page
 * needs to render its own links.
 */
final class ListArtifactVersionLinks
{
    public function __construct(
        private readonly ArtifactVersionFinder $versions,
        private readonly ArtifactLinkRepository $links,
    ) {}

    /**
     * @return array{outgoing: list<ArtifactLink>, incoming: list<ArtifactLink>}
     */
    public function handle(string $versionId): array
    {
        if ($this->versions->findById($versionId) === null) {
            throw new RuntimeException('Artifact version not found in this tenant.');
        }

        return [
            'outgoing' => $this->links->findByFromVersion($versionId),
            'incoming' => $this->links->findByToVersion($versionId),
        ];
    }
}
