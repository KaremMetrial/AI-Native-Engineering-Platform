<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\Approval;
use App\Graph\Domain\ApprovalDecision;
use App\Graph\Domain\ApprovalRepository;
use App\Graph\Domain\ArtifactVersionFinder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Records an approval decision against a specific version (D-278: never
 * against the artifact, which would leave the approval floating relative
 * to the text it was actually given for).
 */
final class ApproveArtifactVersion
{
    public function __construct(
        private readonly ArtifactVersionFinder $versions,
        private readonly ApprovalRepository $approvals,
    ) {}

    public function handle(
        string $tenantId,
        string $artifactVersionId,
        string $approvedBy,
        ApprovalDecision $decision,
        ?string $comment,
    ): Approval {
        if ($this->versions->findById($artifactVersionId) === null) {
            throw new RuntimeException('Artifact version not found in this tenant.');
        }

        $approval = Approval::record(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            artifactVersionId: $artifactVersionId,
            approvedBy: $approvedBy,
            decision: $decision,
            comment: $comment,
        );

        $this->approvals->save($approval);

        return $approval;
    }
}
