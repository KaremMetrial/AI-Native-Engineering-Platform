<?php

declare(strict_types=1);

namespace Tests\Integration\Graph;

use App\Graph\Domain\Project;
use App\Graph\Infrastructure\EloquentProjectRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesGraphFixtures;
use Tests\TestCase;

class EloquentProjectRepositoryTest extends TestCase
{
    use CreatesGraphFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_a_project(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $repository = new EloquentProjectRepository;
        $project = Project::create((string) Str::uuid(), $tenantId, 'Acme Website');
        $repository->save($project);

        $found = $repository->findById($project->id);

        $this->assertNotNull($found);
        $this->assertSame('Acme Website', $found->name());
    }

    public function test_find_all_returns_every_project_for_the_bound_tenant(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $repository = new EloquentProjectRepository;
        $repository->save(Project::create((string) Str::uuid(), $tenantId, 'Acme Website'));
        $repository->save(Project::create((string) Str::uuid(), $tenantId, 'Acme Mobile'));

        $projects = $repository->findAll();

        $this->assertCount(2, $projects);
        $names = array_map(fn (Project $project): string => $project->name(), $projects);
        $this->assertContains('Acme Website', $names);
        $this->assertContains('Acme Mobile', $names);
    }

    public function test_find_all_does_not_see_another_tenants_projects(): void
    {
        $ownTenantId = $this->createTenant();
        $otherTenantId = $this->createTenant();
        $repository = new EloquentProjectRepository;

        $this->app->make(TenantContext::class)->bind($otherTenantId);
        $repository->save(Project::create((string) Str::uuid(), $otherTenantId, 'Other Tenant Project'));

        $this->app->make(TenantContext::class)->bind($ownTenantId);
        $repository->save(Project::create((string) Str::uuid(), $ownTenantId, 'Acme Website'));

        $projects = $repository->findAll();

        $this->assertCount(1, $projects);
        $this->assertSame('Acme Website', $projects[0]->name());
    }
}
