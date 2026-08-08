<?php

declare(strict_types=1);

namespace Tests\Integration\Graph;

use App\Graph\Domain\Approval;
use App\Graph\Domain\ApprovalDecision;
use App\Graph\Domain\Artifact;
use App\Graph\Infrastructure\EloquentApprovalRepository;
use App\Graph\Infrastructure\EloquentArtifactRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesGraphFixtures;
use Tests\TestCase;

class EloquentApprovalRepositoryTest extends TestCase
{
    use CreatesGraphFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_by_artifact_version(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $artifact = Artifact::create((string) Str::uuid(), $tenantId, $projectId, 'contract', (string) Str::uuid(), 'terms', $userId);
        (new EloquentArtifactRepository)->save($artifact);

        $repository = new EloquentApprovalRepository;
        $repository->save(Approval::record(
            (string) Str::uuid(),
            $tenantId,
            $artifact->currentVersionId(),
            $userId,
            ApprovalDecision::Approved,
            'Signed off',
        ));

        $approvals = $repository->findByArtifactVersion($artifact->currentVersionId());

        $this->assertCount(1, $approvals);
        $this->assertSame(ApprovalDecision::Approved, $approvals[0]->decision);
    }
}
