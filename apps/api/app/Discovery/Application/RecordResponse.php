<?php

declare(strict_types=1);

namespace App\Discovery\Application;

use App\Discovery\Domain\QuestionRepository;
use App\Discovery\Domain\Response;
use App\Discovery\Domain\ResponseRepository;
use Illuminate\Support\Str;
use RuntimeException;

final class RecordResponse
{
    public function __construct(
        private readonly QuestionRepository $questions,
        private readonly ResponseRepository $responses,
    ) {}

    public function handle(string $tenantId, string $questionId, string $content, string $respondedBy): Response
    {
        if ($this->questions->findById($questionId) === null) {
            throw new RuntimeException('Question not found in this tenant.');
        }

        $response = Response::record(
            id: (string) Str::uuid(),
            tenantId: $tenantId,
            questionId: $questionId,
            content: $content,
            respondedBy: $respondedBy,
        );

        $this->responses->save($response);

        return $response;
    }
}
