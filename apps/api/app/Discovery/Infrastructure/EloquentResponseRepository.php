<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\Response;
use App\Discovery\Domain\ResponseRepository;

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
}
