<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\Approval;
use App\Graph\Domain\ApprovalRepository;
use App\Graph\Domain\ArtifactVersionFinder;
use RuntimeException;

final class ListApprovalsForVersion
{
    public function __construct(
        private readonly ArtifactVersionFinder $versions,
        private readonly ApprovalRepository $approvals,
    ) {}

    /**
     * @return list<Approval>
     */
    public function handle(string $versionId): array
    {
        if ($this->versions->findById($versionId) === null) {
            throw new RuntimeException('Artifact version not found in this tenant.');
        }

        return $this->approvals->findByArtifactVersion($versionId);
    }
}
