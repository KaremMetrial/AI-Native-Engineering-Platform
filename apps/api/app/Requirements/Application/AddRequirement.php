<?php

declare(strict_types=1);

namespace App\Requirements\Application;

use App\Requirements\Domain\AcceptanceCriterion;
use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementDocumentRepository;
use App\Requirements\Domain\RequirementDocumentStatus;
use App\Requirements\Domain\RequirementRepository;
use Illuminate\Support\Str;
use RuntimeException;

final class AddRequirement
{
    public function __construct(
        private readonly RequirementDocumentRepository $documents,
        private readonly RequirementRepository $requirements,
    ) {}

    /**
     * @param  list<string>  $acceptanceCriteria
     */
    public function handle(string $tenantId, string $documentId, string $text, array $acceptanceCriteria, string $createdBy): Requirement
    {
        $document = $this->documents->findById($documentId);

        if ($document === null) {
            throw new RuntimeException('Requirement document not found in this tenant.');
        }

        if ($document->status() !== RequirementDocumentStatus::Draft) {
            throw new RuntimeException('Cannot add a requirement to an approved document.');
        }

        $requirement = Requirement::draft(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            documentId: $documentId,
            text: $text,
            acceptanceCriteria: array_map(
                static fn (string $description): AcceptanceCriterion => new AcceptanceCriterion($description),
                $acceptanceCriteria,
            ),
            createdBy: $createdBy,
        );

        $this->requirements->save($requirement);

        return $requirement;
    }
}
