<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_me_returns_the_authenticated_users_tenant_and_role(): void
    {
        $register = $this->postJson('/api/register', [
            'tenant_name' => 'Acme',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(201);

        $token = $register->json('token');
        $tenantId = $register->json('tenant.id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'ada@example.test')
            ->assertJsonPath('tenant_id', $tenantId)
            ->assertJsonPath('role', 'owner');
    }

    public function test_me_without_a_token_is_rejected(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_with_an_invalid_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}
