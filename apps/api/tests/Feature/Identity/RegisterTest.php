<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registering_creates_a_tenant_owner_and_token(): void
    {
        $response = $this->postJson('/api/register', [
            'tenant_name' => 'Acme',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('tenant.name', 'Acme')
            ->assertJsonPath('user.email', 'ada@example.test')
            ->assertJsonPath('role', 'owner')
            ->assertJsonStructure(['tenant' => ['id'], 'user' => ['id'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.test']);
        $this->assertDatabaseHas('tenants', ['name' => 'Acme']);
    }

    public function test_registering_with_an_existing_email_is_rejected(): void
    {
        $this->postJson('/api/register', [
            'tenant_name' => 'Acme',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(201);

        $response = $this->postJson('/api/register', [
            'tenant_name' => 'Other Co',
            'name' => 'Someone Else',
            'email' => 'ada@example.test',
            'password' => 'another-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_registering_requires_all_fields(): void
    {
        $this->postJson('/api/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_name', 'name', 'email', 'password']);
    }
}
