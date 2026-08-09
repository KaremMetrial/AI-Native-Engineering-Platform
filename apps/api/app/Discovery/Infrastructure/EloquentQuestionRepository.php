<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\Question;
use App\Discovery\Domain\QuestionRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

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

    public function findAllBySession(string $sessionId): array
    {
        $rows = DB::table('discovery_questions')
            ->where('session_id', $sessionId)
            ->orderBy('sequence')
            ->get();

        return array_values($rows->map(fn (mixed $row): Question => $this->rowToDomain($row))->all());
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

    private function rowToDomain(mixed $row): Question
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from discovery_questions.');
        }

        return new Question(
            id: $this->requireString($row->id),
            tenantId: $this->requireString($row->tenant_id),
            sessionId: $this->requireString($row->session_id),
            prompt: $this->requireString($row->prompt),
            sequence: $this->requireInt($row->sequence),
            createdBy: $this->requireString($row->created_by),
            createdAt: new DateTimeImmutable($this->requireString($row->created_at)),
        );
    }

    private function requireString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected a string column value.');
        }

        return $value;
    }

    private function requireInt(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new RuntimeException('Expected a numeric column value.');
        }

        return (int) $value;
    }
}
