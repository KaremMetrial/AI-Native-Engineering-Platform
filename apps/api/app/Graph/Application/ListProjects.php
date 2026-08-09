<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\Project;
use App\Graph\Domain\ProjectRepository;

final class ListProjects
{
    public function __construct(
        private readonly ProjectRepository $projects,
    ) {}

    /**
     * @return list<Project>
     */
    public function handle(): array
    {
        return $this->projects->findAll();
    }
}
