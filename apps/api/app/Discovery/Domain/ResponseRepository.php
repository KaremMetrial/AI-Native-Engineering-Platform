<?php

declare(strict_types=1);

namespace App\Discovery\Domain;

interface ResponseRepository
{
    public function save(Response $response): void;

    public function findById(string $id): ?Response;

    /**
     * @return list<Response>
     */
    public function findAllByQuestion(string $questionId): array;
}
