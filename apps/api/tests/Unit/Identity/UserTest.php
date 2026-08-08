<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Identity\Domain\User;
use App\Identity\Domain\UserStatus;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_register_creates_an_active_user(): void
    {
        $user = User::register(id: 'user-1', name: 'Ada', email: 'ada@example.test', hashedPassword: 'hashed');

        $this->assertSame('Ada', $user->name());
        $this->assertSame('ada@example.test', $user->email());
        $this->assertSame('hashed', $user->hashedPassword());
        $this->assertSame(UserStatus::Active, $user->status());
        $this->assertTrue($user->isActive());
    }
}
