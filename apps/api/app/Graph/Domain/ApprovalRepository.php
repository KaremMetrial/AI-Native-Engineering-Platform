<?php

declare(strict_types=1);

namespace App\Graph\Domain;

interface ApprovalRepository
{
    public function save(Approval $approval): void;

    /**
     * @return list<Approval>
     */
    public function findByArtifactVersion(string $artifactVersionId): array;
}
