<?php

declare(strict_types=1);

namespace App\Requirements\Application;

use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementRepository;

final class FindRequirement
{
    public function __construct(
        private readonly RequirementRepository $requirements,
    ) {}

    public function handle(string $requirementId): ?Requirement
    {
        return $this->requirements->findById($requirementId);
    }
}
