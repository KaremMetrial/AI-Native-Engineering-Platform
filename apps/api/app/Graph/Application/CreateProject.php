<?php

declare(strict_types=1);

namespace App\Graph\Application;

use App\Graph\Domain\Project;
use App\Graph\Domain\ProjectRepository;
use Illuminate\Support\Str;

/**
 * Minimal by design -- see app/Graph/Domain/Project.php. This exists so
 * an Artifact has somewhere to attach, not as the "project workspace"
 * deliverable.
 */
final class CreateProject
{
    public function __construct(
        private readonly ProjectRepository $projects,
    ) {}

    public function handle(string $tenantId, string $name): Project
    {
        $project = Project::create(id: (string) Str::uuid(), tenantId: $tenantId, name: $name);

        $this->projects->save($project);

        return $project;
    }
}
