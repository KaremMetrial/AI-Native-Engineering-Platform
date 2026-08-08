<?php

declare(strict_types=1);

namespace App\Requirements\Application;

use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementRepository;
use RuntimeException;

final class ApproveRequirement
{
    public function __construct(
        private readonly RequirementRepository $requirements,
    ) {}

    public function handle(string $requirementId): Requirement
    {
        $requirement = $this->requirements->findById($requirementId);

        if ($requirement === null) {
            throw new RuntimeException('Requirement not found in this tenant.');
        }

        $requirement->approve();

        $this->requirements->save($requirement);

        return $requirement;
    }
}
