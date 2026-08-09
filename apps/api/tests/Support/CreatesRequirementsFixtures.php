<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Requirements\Domain\AcceptanceCriterion;
use App\Requirements\Domain\DocumentType;
use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Infrastructure\EloquentRequirementDocumentRepository;
use App\Requirements\Infrastructure\EloquentRequirementRepository;
use Illuminate\Support\Str;

/**
 * Shared fixture builders for Requirements module tests. Reuses
 * CreatesGraphFixtures for tenant/user/project, since a RequirementDocument
 * structurally requires a project (FK to the shared kernel).
 */
trait CreatesRequirementsFixtures
{
    use CreatesGraphFixtures;

    private function createRequirementDocument(string $tenantId, string $projectId, string $createdBy): string
    {
        $document = RequirementDocument::create((string) Str::uuid(), $tenantId, $projectId, DocumentType::Brd, 'Acme BRD', $createdBy);
        (new EloquentRequirementDocumentRepository)->save($document);

        return $document->id;
    }

    private function createRequirement(string $tenantId, string $documentId, string $createdBy): string
    {
        $requirement = Requirement::draft(
            (string) Str::uuid(),
            $tenantId,
            $documentId,
            'The system shall allow users to log in.',
            [new AcceptanceCriterion('Login succeeds with valid credentials.')],
            $createdBy,
        );
        (new EloquentRequirementRepository)->save($requirement);

        return $requirement->id;
    }
}
