<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\DiscoverySessionRepository;
use App\Discovery\Domain\Question;
use App\Discovery\Domain\QuestionRepository;
use RuntimeException;

final class ListQuestionsForSession
{
    public function __construct(
        private readonly DiscoverySessionRepository $sessions,
        private readonly QuestionRepository $questions,
    ) {}

    /**
     * @return list<Question>
     */
    public function handle(string $sessionId): array
    {
        if ($this->sessions->findById($sessionId) === null) {
            throw new RuntimeException('Discovery session not found in this tenant.');
        }

        return $this->questions->findAllBySession($sessionId);
    }
}
