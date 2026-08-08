<?php

declare(strict_types=1);

namespace Tests\Integration\Graph;

use App\Graph\Domain\Artifact;
use App\Graph\Domain\ArtifactLink;
use App\Graph\Domain\LinkType;
use App\Graph\Infrastructure\EloquentArtifactLinkRepository;
use App\Graph\Infrastructure\EloquentArtifactRepository;
use App\Graph\Infrastructure\PostgresImpactTraversal;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesGraphFixtures;
use Tests\TestCase;

class PostgresImpactTraversalTest extends TestCase
{
    use CreatesGraphFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_traverse_follows_a_chain_of_links_in_reverse(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $artifacts = new EloquentArtifactRepository;
        $links = new EloquentArtifactLinkRepository;

        // requirement <-implements- task <-verifies- test
        $requirement = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'srs', (string) Str::uuid(), 'req', $userId);
        $artifacts->save($requirement);
        $task = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'task', (string) Str::uuid(), 'task', $userId);
        $artifacts->save($task);
        $test = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'test_case', (string) Str::uuid(), 'test', $userId);
        $artifacts->save($test);

        $links->save(ArtifactLink::create((string) Str::uuid(), $tenantId, $task->currentVersionId(), $requirement->currentVersionId(), LinkType::Implements, $userId));
        $links->save(ArtifactLink::create((string) Str::uuid(), $tenantId, $test->currentVersionId(), $task->currentVersionId(), LinkType::Verifies, $userId));

        $reached = (new PostgresImpactTraversal)->traverse($requirement->currentVersionId(), 5);

        $this->assertContains($task->currentVersionId(), $reached);
        $this->assertContains($test->currentVersionId(), $reached);
        $this->assertNotContains($requirement->currentVersionId(), $reached);
    }

    public function test_traverse_respects_the_depth_bound(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $artifacts = new EloquentArtifactRepository;
        $links = new EloquentArtifactLinkRepository;

        $requirement = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'srs', (string) Str::uuid(), 'req', $userId);
        $artifacts->save($requirement);
        $task = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'task', (string) Str::uuid(), 'task', $userId);
        $artifacts->save($task);

        $links->save(ArtifactLink::create((string) Str::uuid(), $tenantId, $task->currentVersionId(), $requirement->currentVersionId(), LinkType::Implements, $userId));

        $reached = (new PostgresImpactTraversal)->traverse($requirement->currentVersionId(), 0);

        $this->assertSame([], $reached);
    }

    public function test_traverse_deduplicates_a_node_reached_through_two_paths(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $artifacts = new EloquentArtifactRepository;
        $links = new EloquentArtifactLinkRepository;

        // requirement <- taskA, requirement <- taskB, and both taskA and
        // taskB are also verified by the same test -- "test" is
        // reachable from "requirement" through two distinct paths.
        $requirement = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'srs', (string) Str::uuid(), 'req', $userId);
        $artifacts->save($requirement);
        $taskA = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'task', (string) Str::uuid(), 'taskA', $userId);
        $artifacts->save($taskA);
        $taskB = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'task', (string) Str::uuid(), 'taskB', $userId);
        $artifacts->save($taskB);
        $test = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'test_case', (string) Str::uuid(), 'test', $userId);
        $artifacts->save($test);

        $links->save(ArtifactLink::create((string) Str::uuid(), $tenantId, $taskA->currentVersionId(), $requirement->currentVersionId(), LinkType::Implements, $userId));
        $links->save(ArtifactLink::create((string) Str::uuid(), $tenantId, $taskB->currentVersionId(), $requirement->currentVersionId(), LinkType::Implements, $userId));
        $links->save(ArtifactLink::create((string) Str::uuid(), $tenantId, $test->currentVersionId(), $taskA->currentVersionId(), LinkType::Verifies, $userId));
        $links->save(ArtifactLink::create((string) Str::uuid(), $tenantId, $test->currentVersionId(), $taskB->currentVersionId(), LinkType::Verifies, $userId));

        $reached = (new PostgresImpactTraversal)->traverse($requirement->currentVersionId(), 5);

        $testOccurrences = array_filter($reached, fn (string $id): bool => $id === $test->currentVersionId());
        $this->assertCount(1, $testOccurrences);
    }
}
