<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\Question;
use App\Discovery\Domain\QuestionRepository;

class EloquentQuestionRepository implements QuestionRepository
{
    public function save(Question $question): void
    {
        EloquentQuestion::query()->firstOrCreate(
            ['id' => $question->id],
            [
                'tenant_id' => $question->tenantId,
                'session_id' => $question->sessionId,
                'prompt' => $question->prompt,
                'sequence' => $question->sequence,
                'created_by' => $question->createdBy,
                'created_at' => $question->createdAt,
            ],
        );
    }

    public function findById(string $id): ?Question
    {
        $model = EloquentQuestion::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function countBySession(string $sessionId): int
    {
        return EloquentQuestion::query()->where('session_id', $sessionId)->count();
    }

    private function toDomain(EloquentQuestion $model): Question
    {
        return new Question(
            id: $model->id,
            tenantId: $model->tenant_id,
            sessionId: $model->session_id,
            prompt: $model->prompt,
            sequence: $model->sequence,
            createdBy: $model->created_by,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
