<?php

declare(strict_types=1);

namespace Tests\Unit\Graph;

use App\Graph\Domain\Project;
use App\Graph\Domain\ProjectStatus;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    public function test_create_produces_an_active_project(): void
    {
        $project = Project::create('project-1', 'tenant-1', 'Acme Website');

        $this->assertSame('Acme Website', $project->name());
        $this->assertSame(ProjectStatus::Active, $project->status());
    }
}
