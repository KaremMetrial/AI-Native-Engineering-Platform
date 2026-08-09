<?php

declare(strict_types=1);

namespace Tests\Integration\Requirements;

use App\Requirements\Domain\DocumentType;
use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Domain\RequirementDocumentStatus;
use App\Requirements\Infrastructure\EloquentRequirementDocumentRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesRequirementsFixtures;
use Tests\TestCase;

class EloquentRequirementDocumentRepositoryTest extends TestCase
{
    use CreatesRequirementsFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_a_document(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentRequirementDocumentRepository;
        $document = RequirementDocument::create((string) Str::uuid(), $tenantId, $projectId, DocumentType::Srs, 'Acme SRS', $userId);
        $repository->save($document);

        $found = $repository->findById($document->id);

        $this->assertNotNull($found);
        $this->assertSame('Acme SRS', $found->title);
        $this->assertSame(DocumentType::Srs, $found->type);
        $this->assertSame(RequirementDocumentStatus::Draft, $found->status());
    }

    public function test_a_second_save_persists_the_approved_state(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentRequirementDocumentRepository;
        $document = RequirementDocument::create((string) Str::uuid(), $tenantId, $projectId, DocumentType::Brd, 'Acme BRD', $userId);
        $repository->save($document);

        $document->approve();
        $repository->save($document);

        $found = $repository->findById($document->id);

        $this->assertNotNull($found);
        $this->assertSame(RequirementDocumentStatus::Approved, $found->status());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentRequirementDocumentRepository)->findById((string) Str::uuid()));
    }

    public function test_find_all_returns_every_document_for_the_bound_tenant(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentRequirementDocumentRepository;
        $repository->save(RequirementDocument::create((string) Str::uuid(), $tenantId, $projectId, DocumentType::Brd, 'Acme BRD', $userId));
        $repository->save(RequirementDocument::create((string) Str::uuid(), $tenantId, $projectId, DocumentType::Srs, 'Acme SRS', $userId));

        $documents = $repository->findAll();

        $this->assertCount(2, $documents);
        $titles = array_map(fn (RequirementDocument $document): string => $document->title, $documents);
        $this->assertContains('Acme BRD', $titles);
        $this->assertContains('Acme SRS', $titles);
    }

    public function test_find_all_does_not_see_another_tenants_documents(): void
    {
        $ownTenantId = $this->createTenant();
        $otherTenantId = $this->createTenant();
        $repository = new EloquentRequirementDocumentRepository;

        $this->app->make(TenantContext::class)->bind($otherTenantId);
        $otherProjectId = $this->createProject($otherTenantId);
        $otherUserId = $this->createUser();
        $repository->save(RequirementDocument::create((string) Str::uuid(), $otherTenantId, $otherProjectId, DocumentType::Brd, 'Other Tenant Document', $otherUserId));

        $this->app->make(TenantContext::class)->bind($ownTenantId);
        $ownProjectId = $this->createProject($ownTenantId);
        $ownUserId = $this->createUser();
        $repository->save(RequirementDocument::create((string) Str::uuid(), $ownTenantId, $ownProjectId, DocumentType::Brd, 'Acme BRD', $ownUserId));

        $documents = $repository->findAll();

        $this->assertCount(1, $documents);
        $this->assertSame('Acme BRD', $documents[0]->title);
    }
}
