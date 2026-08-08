<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\TokenIssuer;
use App\Identity\Domain\UserRepository;
use Illuminate\Contracts\Hashing\Hasher;

final class AuthenticateUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TokenIssuer $tokens,
        private readonly Hasher $hasher,
    ) {}

    public function handle(string $email, string $password): AuthenticateUserResult
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || ! $this->hasher->check($password, $user->hashedPassword())) {
            return AuthenticateUserResult::failed();
        }

        if (! $user->isActive()) {
            return AuthenticateUserResult::failed();
        }

        return AuthenticateUserResult::succeeded($user, $this->tokens->issue($user));
    }
}
