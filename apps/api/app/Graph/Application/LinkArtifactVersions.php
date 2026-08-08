<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\ArtifactLink;
use App\Graph\Domain\ArtifactLinkRepository;
use App\Graph\Domain\ArtifactVersionFinder;
use App\Graph\Domain\LinkType;
use Illuminate\Support\Str;
use RuntimeException;

final class LinkArtifactVersions
{
    public function __construct(
        private readonly ArtifactVersionFinder $versions,
        private readonly ArtifactLinkRepository $links,
    ) {}

    public function handle(
        string $tenantId,
        string $fromVersionId,
        string $toVersionId,
        LinkType $linkType,
        string $createdBy,
    ): ArtifactLink {
        if ($this->versions->findById($fromVersionId) === null) {
            throw new RuntimeException('Source version not found in this tenant.');
        }

        if ($this->versions->findById($toVersionId) === null) {
            throw new RuntimeException('Target version not found in this tenant.');
        }

        $link = ArtifactLink::create(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            fromVersionId: $fromVersionId,
            toVersionId: $toVersionId,
            linkType: $linkType,
            createdBy: $createdBy,
        );

        $this->links->save($link);

        return $link;
    }
}
