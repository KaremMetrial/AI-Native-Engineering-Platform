<?php

declare(strict_types=1);

namespace App\Requirements\Application;

use App\Graph\Application\FindProject;
use App\Requirements\Domain\DocumentType;
use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Domain\RequirementDocumentRepository;
use Illuminate\Support\Str;
use RuntimeException;

final class CreateRequirementDocument
{
    public function __construct(
        private readonly FindProject $findProject,
        private readonly RequirementDocumentRepository $documents,
    ) {}

    public function handle(string $tenantId, string $projectId, DocumentType $type, string $title, string $createdBy): RequirementDocument
    {
        if ($this->findProject->handle($projectId) === null) {
            throw new RuntimeException('Project not found in this tenant.');
        }

        $document = RequirementDocument::create(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            projectId: $projectId,
            type: $type,
            title: $title,
            createdBy: $createdBy,
        );

        $this->documents->save($document);

        return $document;
    }
}
