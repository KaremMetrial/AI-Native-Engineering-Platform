<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\User;

final class AuthenticateUserResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly ?User $user,
        public readonly ?string $token,
    ) {}

    public static function succeeded(User $user, string $token): self
    {
        return new self(true, $user, $token);
    }

    public static function failed(): self
    {
        return new self(false, null, null);
    }
}
