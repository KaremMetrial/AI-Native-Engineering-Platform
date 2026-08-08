<?php

declare(strict_types=1);

namespace Tests\Integration\Requirements;

use App\Requirements\Domain\AcceptanceCriterion;
use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementStatus;
use App\Requirements\Infrastructure\EloquentRequirementRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesRequirementsFixtures;
use Tests\TestCase;

class EloquentRequirementRepositoryTest extends TestCase
{
    use CreatesRequirementsFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_a_requirement_with_its_acceptance_criteria(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $documentId = $this->createRequirementDocument($tenantId, $projectId, $userId);

        $repository = new EloquentRequirementRepository;
        $requirement = Requirement::draft(
            (string) Str::uuid(),
            $tenantId,
            $documentId,
            'The system shall allow login via email and password.',
            [new AcceptanceCriterion('Given valid credentials, the user is logged in.')],
            $userId,
        );
        $repository->save($requirement);

        $found = $repository->findById($requirement->id);

        $this->assertNotNull($found);
        $this->assertSame('The system shall allow login via email and password.', $found->text);
        $this->assertCount(1, $found->acceptanceCriteria());
        $this->assertSame('Given valid credentials, the user is logged in.', $found->acceptanceCriteria()[0]->description);
        $this->assertSame(RequirementStatus::Draft, $found->status());
    }

    public function test_count_by_document_and_count_approved_by_document(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $documentId = $this->createRequirementDocument($tenantId, $projectId, $userId);

        $repository = new EloquentRequirementRepository;

        $first = Requirement::draft((string) Str::uuid(), $tenantId, $documentId, 'First', [], $userId);
        $first->approve();
        $repository->save($first);

        $second = Requirement::draft((string) Str::uuid(), $tenantId, $documentId, 'Second', [], $userId);
        $repository->save($second);

        $this->assertSame(2, $repository->countByDocument($documentId));
        $this->assertSame(1, $repository->countApprovedByDocument($documentId));
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentRequirementRepository)->findById((string) Str::uuid()));
    }
}
