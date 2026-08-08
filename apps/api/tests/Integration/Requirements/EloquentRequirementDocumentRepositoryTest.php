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
}
