<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_with_correct_credentials_returns_a_token(): void
    {
        $this->postJson('/api/register', [
            'tenant_name' => 'Acme',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(201);

        $response = $this->postJson('/api/login', [
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'ada@example.test')
            ->assertJsonStructure(['user' => ['id'], 'token']);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $this->postJson('/api/register', [
            'tenant_name' => 'Acme',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(201);

        $response = $this->postJson('/api/login', [
            'email' => 'ada@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_with_unknown_email_is_rejected(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.test',
            'password' => 'anything',
        ]);

        $response->assertStatus(401);
    }
}
