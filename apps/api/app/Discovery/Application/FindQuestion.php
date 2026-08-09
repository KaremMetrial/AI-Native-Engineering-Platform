<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\Question;
use App\Discovery\Domain\QuestionRepository;

final class FindQuestion
{
    public function __construct(
        private readonly QuestionRepository $questions,
    ) {}

    public function handle(string $questionId): ?Question
    {
        return $this->questions->findById($questionId);
    }
}
