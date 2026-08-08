<?php

declare(strict_types=1);

namespace Tests\Integration\Graph;

use App\Graph\Domain\Artifact;
use App\Graph\Infrastructure\EloquentArtifactRepository;
use App\Graph\Infrastructure\EloquentArtifactVersionFinder;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesGraphFixtures;
use Tests\TestCase;

class EloquentArtifactVersionFinderTest extends TestCase
{
    use CreatesGraphFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_find_by_id_locates_a_version_without_loading_the_parent_artifact(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $artifact = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'brd', (string) Str::uuid(), 'Hello', $userId);
        (new EloquentArtifactRepository)->save($artifact);

        $found = (new EloquentArtifactVersionFinder)->findById($artifact->currentVersionId());

        $this->assertNotNull($found);
        $this->assertSame('Hello', $found->content);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentArtifactVersionFinder)->findById((string) Str::uuid()));
    }
}
