<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\TokenIssuer;
use App\Identity\Domain\User;
use RuntimeException;

class SanctumTokenIssuer implements TokenIssuer
{
    public function issue(User $user): string
    {
        $model = EloquentUser::query()->find($user->id);

        if ($model === null) {
            throw new RuntimeException("Cannot issue a token for unknown user [{$user->id}].");
        }

        return $model->createToken('api')->plainTextToken;
    }
}
