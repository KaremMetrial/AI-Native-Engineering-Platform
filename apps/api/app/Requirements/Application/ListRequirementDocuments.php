<?php

declare(strict_types=1);

namespace App\Requirements\Application;

use App\Requirements\Domain\RequirementDocument;
use App\Requirements\Domain\RequirementDocumentRepository;

final class ListRequirementDocuments
{
    public function __construct(
        private readonly RequirementDocumentRepository $documents,
    ) {}

    /**
     * @return list<RequirementDocument>
     */
    public function handle(): array
    {
        return $this->documents->findAll();
    }
}
