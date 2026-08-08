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
}
