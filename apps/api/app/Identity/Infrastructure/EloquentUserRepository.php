<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\UserStatus;

class EloquentUserRepository implements UserRepository
{
    public function save(User $user): void
    {
        EloquentUser::query()->updateOrCreate(
            ['id' => $user->id],
            [
                'name' => $user->name(),
                'email' => $user->email(),
                'password' => $user->hashedPassword(),
                'status' => $user->status()->value,
            ],
        );
    }

    public function findById(string $id): ?User
    {
        $model = EloquentUser::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByEmail(string $email): ?User
    {
        $model = EloquentUser::query()->where('email', $email)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function existsByEmail(string $email): bool
    {
        return EloquentUser::query()->where('email', $email)->exists();
    }

    private function toDomain(EloquentUser $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            hashedPassword: $model->password,
            status: UserStatus::from($model->status),
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
