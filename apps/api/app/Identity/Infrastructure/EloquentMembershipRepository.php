<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\Membership;
use App\Identity\Domain\MembershipRepository;
use App\Identity\Domain\MembershipStatus;
use App\Identity\Domain\Role;

class EloquentMembershipRepository implements MembershipRepository
{
    public function save(Membership $membership): void
    {
        EloquentMembership::query()->updateOrCreate(
            ['id' => $membership->id],
            [
                'tenant_id' => $membership->tenantId,
                'user_id' => $membership->userId,
                'role' => $membership->role()->value,
                'status' => $membership->status()->value,
            ],
        );
    }

    public function findByTenantAndUser(string $tenantId, string $userId): ?Membership
    {
        $model = EloquentMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByUser(string $userId): array
    {
        $models = EloquentMembership::query()
            ->where('user_id', $userId)
            ->get()
            ->map(fn (EloquentMembership $model): Membership => $this->toDomain($model))
            ->all();

        return array_values($models);
    }

    private function toDomain(EloquentMembership $model): Membership
    {
        return new Membership(
            id: $model->id,
            tenantId: $model->tenant_id,
            userId: $model->user_id,
            role: Role::from($model->role),
            status: MembershipStatus::from($model->status),
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
