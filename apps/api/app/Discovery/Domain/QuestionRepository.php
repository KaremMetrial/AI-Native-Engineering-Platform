<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

interface QuestionRepository
{
    public function save(Question $question): void;

    public function findById(string $id): ?Question;

    public function countBySession(string $sessionId): int;
}
