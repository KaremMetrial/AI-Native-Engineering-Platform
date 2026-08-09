<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\Response;
use App\Discovery\Domain\ResponseRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

class EloquentResponseRepository implements ResponseRepository
{
    public function save(Response $response): void
    {
        EloquentResponse::query()->firstOrCreate(
            ['id' => $response->id],
            [
                'tenant_id' => $response->tenantId,
                'question_id' => $response->questionId,
                'content' => $response->content,
                'responded_by' => $response->respondedBy,
                'created_at' => $response->createdAt,
            ],
        );
    }

    public function findById(string $id): ?Response
    {
        $model = EloquentResponse::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findAllByQuestion(string $questionId): array
    {
        $rows = DB::table('discovery_responses')
            ->where('question_id', $questionId)
            ->orderBy('created_at')
            ->get();

        return array_values($rows->map(fn (mixed $row): Response => $this->rowToDomain($row))->all());
    }

    private function toDomain(EloquentResponse $model): Response
    {
        return new Response(
            id: $model->id,
            tenantId: $model->tenant_id,
            questionId: $model->question_id,
            content: $model->content,
            respondedBy: $model->responded_by,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }

    private function rowToDomain(mixed $row): Response
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Expected a stdClass row from discovery_responses.');
        }

        return new Response(
            id: $this->requireString($row->id),
            tenantId: $this->requireString($row->tenant_id),
            questionId: $this->requireString($row->question_id),
            content: $this->requireString($row->content),
            respondedBy: $this->requireString($row->responded_by),
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
}
