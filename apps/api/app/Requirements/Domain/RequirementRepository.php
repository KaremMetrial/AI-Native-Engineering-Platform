<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

interface RequirementRepository
{
    public function save(Requirement $requirement): void;

    public function findById(string $id): ?Requirement;

    public function countByDocument(string $documentId): int;

    public function countApprovedByDocument(string $documentId): int;

    /**
     * @return list<Requirement>
     */
    public function findAllByDocument(string $documentId): array;
}
