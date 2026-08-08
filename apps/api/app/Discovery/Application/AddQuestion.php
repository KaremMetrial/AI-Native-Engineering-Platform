<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\DiscoverySessionRepository;
use App\Discovery\Domain\Question;
use App\Discovery\Domain\QuestionRepository;
use App\Discovery\Domain\SessionStatus;
use Illuminate\Support\Str;
use RuntimeException;

final class AddQuestion
{
    public function __construct(
        private readonly DiscoverySessionRepository $sessions,
        private readonly QuestionRepository $questions,
    ) {}

    public function handle(string $tenantId, string $sessionId, string $prompt, string $createdBy): Question
    {
        $session = $this->sessions->findById($sessionId);

        if ($session === null) {
            throw new RuntimeException('Discovery session not found in this tenant.');
        }

        if ($session->status() !== SessionStatus::InProgress) {
            throw new RuntimeException('Cannot add a question to a completed discovery session.');
        }

        $question = Question::ask(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            sessionId: $sessionId,
            prompt: $prompt,
            sequence: $this->questions->countBySession($sessionId) + 1,
            createdBy: $createdBy,
        );

        $this->questions->save($question);

        return $question;
    }
}
