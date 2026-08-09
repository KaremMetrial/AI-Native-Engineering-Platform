<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\QuestionRepository;
use App\Discovery\Domain\Response;
use App\Discovery\Domain\ResponseRepository;
use RuntimeException;

final class ListResponsesForQuestion
{
    public function __construct(
        private readonly QuestionRepository $questions,
        private readonly ResponseRepository $responses,
    ) {}

    /**
     * @return list<Response>
     */
    public function handle(string $questionId): array
    {
        if ($this->questions->findById($questionId) === null) {
            throw new RuntimeException('Discovery question not found in this tenant.');
        }

        return $this->responses->findAllByQuestion($questionId);
    }
}
