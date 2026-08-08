<?php

declare(strict_types=1);

namespace Tests\Unit\Requirements;

use App\Requirements\Domain\DocumentType;
use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Domain\RequirementDocumentAlreadyApproved;
use App\Requirements\Domain\RequirementDocumentStatus;
use Tests\TestCase;

class RequirementDocumentTest extends TestCase
{
    public function test_create_produces_a_draft_document(): void
    {
        $document = RequirementDocument::create('document-1', 'tenant-1', 'project-1', DocumentType::Brd, 'Acme BRD', 'user-1');

        $this->assertSame(RequirementDocumentStatus::Draft, $document->status());
        $this->assertSame(DocumentType::Brd, $document->type);
    }

    public function test_approve_transitions_to_approved(): void
    {
        $document = RequirementDocument::create('document-1', 'tenant-1', 'project-1', DocumentType::Brd, 'Acme BRD', 'user-1');

        $document->approve();

        $this->assertSame(RequirementDocumentStatus::Approved, $document->status());
    }

    public function test_approving_an_already_approved_document_throws(): void
    {
        $document = RequirementDocument::create('document-1', 'tenant-1', 'project-1', DocumentType::Brd, 'Acme BRD', 'user-1');
        $document->approve();

        $this->expectException(RequirementDocumentAlreadyApproved::class);

        $document->approve();
    }
}
