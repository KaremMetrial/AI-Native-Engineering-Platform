<?php

declare(strict_types=1);

namespace Tests\Integration\Identity;

use App\Identity\Domain\User;
use App\Identity\Infrastructure\EloquentUserRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class EloquentUserRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_save_then_find_by_id_round_trips_a_user(): void
    {
        $repository = new EloquentUserRepository;
        $email = Str::uuid().'@example.test';
        $user = User::register(id: (string) Str::uuid(), name: 'Ada', email: $email, hashedPassword: 'hashed');

        $repository->save($user);
        $found = $repository->findById($user->id);

        $this->assertNotNull($found);
        $this->assertSame('Ada', $found->name());
        $this->assertSame($email, $found->email());
    }

    public function test_find_by_email(): void
    {
        $repository = new EloquentUserRepository;
        $email = Str::uuid().'@example.test';
        $user = User::register(id: (string) Str::uuid(), name: 'Ada', email: $email, hashedPassword: 'hashed');
        $repository->save($user);

        $found = $repository->findByEmail($email);

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    public function test_exists_by_email(): void
    {
        $repository = new EloquentUserRepository;
        $email = Str::uuid().'@example.test';

        $this->assertFalse($repository->existsByEmail($email));

        $repository->save(User::register(id: (string) Str::uuid(), name: 'Ada', email: $email, hashedPassword: 'hashed'));

        $this->assertTrue($repository->existsByEmail($email));
    }
}
