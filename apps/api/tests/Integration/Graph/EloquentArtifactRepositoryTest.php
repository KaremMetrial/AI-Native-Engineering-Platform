<?php

declare(strict_types=1);

namespace Tests\Integration\Graph;

use App\Graph\Domain\Artifact;
use App\Graph\Domain\Lineage;
use App\Graph\Infrastructure\EloquentArtifactRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesGraphFixtures;
use Tests\TestCase;

class EloquentArtifactRepositoryTest extends TestCase
{
    use CreatesGraphFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_an_artifact_with_its_first_version(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentArtifactRepository;
        $artifact = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'brd', (string) Str::uuid(), 'Hello', $userId);
        $repository->save($artifact);

        $found = $repository->findById($artifact->id);

        $this->assertNotNull($found);
        $this->assertSame($artifact->currentVersionId(), $found->currentVersionId());
        $this->assertCount(1, $found->versions());
        $this->assertSame('Hello', $found->versions()[0]->content);
    }

    public function test_a_second_save_persists_a_newly_recorded_version_without_duplicating_the_first(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentArtifactRepository;
        $artifact = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'brd', (string) Str::uuid(), 'v1', $userId);
        $repository->save($artifact);

        $artifact->recordNewVersion((string) Str::uuid(), 'v2', Lineage::human(), $userId);
        $repository->save($artifact);

        $found = $repository->findById($artifact->id);

        $this->assertNotNull($found);
        $this->assertCount(2, $found->versions());
        $this->assertSame('v1', $found->versions()[0]->content);
        $this->assertSame('v2', $found->versions()[1]->content);
        $this->assertSame(2, $found->versions()[1]->versionNumber);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentArtifactRepository)->findById((string) Str::uuid()));
    }

    public function test_find_all_for_project_returns_each_artifact_with_its_versions(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentArtifactRepository;

        $requirement = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'srs', (string) Str::uuid(), 'req v1', $userId);
        $requirement->recordNewVersion((string) Str::uuid(), 'req v2', Lineage::human(), $userId);
        $repository->save($requirement);

        $task = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'task', (string) Str::uuid(), 'task v1', $userId);
        $repository->save($task);

        $found = $repository->findAllForProject($projectId);

        $this->assertCount(2, $found);
        $byId = [];
        foreach ($found as $artifact) {
            $byId[$artifact->id] = $artifact;
        }
        $this->assertCount(2, $byId[$requirement->id]->versions());
        $this->assertCount(1, $byId[$task->id]->versions());
    }

    public function test_find_all_for_project_returns_an_empty_list_for_a_project_with_no_artifacts(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);

        $this->assertSame([], (new EloquentArtifactRepository)->findAllForProject($projectId));
    }
}
