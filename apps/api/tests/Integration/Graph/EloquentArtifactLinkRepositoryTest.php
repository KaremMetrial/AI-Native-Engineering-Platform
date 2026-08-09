<?php

declare(strict_types=1);

namespace Tests\Integration\Graph;

use App\Graph\Domain\Artifact;
use App\Graph\Domain\ArtifactLink;
use App\Graph\Domain\LinkType;
use App\Graph\Infrastructure\EloquentArtifactLinkRepository;
use App\Graph\Infrastructure\EloquentArtifactRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesGraphFixtures;
use Tests\TestCase;

class EloquentArtifactLinkRepositoryTest extends TestCase
{
    use CreatesGraphFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_by_id_round_trips_a_link(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $artifacts = new EloquentArtifactRepository;

        $requirement = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'srs', (string) Str::uuid(), 'req', $userId);
        $artifacts->save($requirement);
        $task = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'task', (string) Str::uuid(), 'task', $userId);
        $artifacts->save($task);

        $link = ArtifactLink::create(
            (string) Str::uuid(),
            $tenantId,
            $task->currentVersionId(),
            $requirement->currentVersionId(),
            LinkType::Implements,
            $userId,
        );

        $repository = new EloquentArtifactLinkRepository;
        $repository->save($link);

        $found = $repository->findById($link->id);

        $this->assertNotNull($found);
        $this->assertSame(LinkType::Implements, $found->linkType);
    }

    public function test_find_by_to_version_finds_reverse_links(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $artifacts = new EloquentArtifactRepository;

        $requirement = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'srs', (string) Str::uuid(), 'req', $userId);
        $artifacts->save($requirement);
        $task = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'task', (string) Str::uuid(), 'task', $userId);
        $artifacts->save($task);

        $repository = new EloquentArtifactLinkRepository;
        $repository->save(ArtifactLink::create(
            (string) Str::uuid(),
            $tenantId,
            $task->currentVersionId(),
            $requirement->currentVersionId(),
            LinkType::Implements,
            $userId,
        ));

        $reverse = $repository->findByToVersion($requirement->currentVersionId());

        $this->assertCount(1, $reverse);
        $this->assertSame($task->currentVersionId(), $reverse[0]->fromVersionId);
    }

    public function test_find_by_from_version_finds_forward_links(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $artifacts = new EloquentArtifactRepository;

        $requirement = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'srs', (string) Str::uuid(), 'req', $userId);
        $artifacts->save($requirement);
        $task = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'task', (string) Str::uuid(), 'task', $userId);
        $artifacts->save($task);

        $repository = new EloquentArtifactLinkRepository;
        $repository->save(ArtifactLink::create(
            (string) Str::uuid(),
            $tenantId,
            $task->currentVersionId(),
            $requirement->currentVersionId(),
            LinkType::Implements,
            $userId,
        ));

        $forward = $repository->findByFromVersion($task->currentVersionId());

        $this->assertCount(1, $forward);
        $this->assertSame($requirement->currentVersionId(), $forward[0]->toVersionId);
    }
}
