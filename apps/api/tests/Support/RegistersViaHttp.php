<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Shared helper for Feature tests that need a real, authenticated tenant
 * owner -- registering through the actual HTTP endpoint rather than
 * seeding repositories directly, since these tests exist to prove the
 * full request pipeline (auth:sanctum + BindTenantContext) works, not
 * just the use case underneath it.
 */
trait RegistersViaHttp
{
    /**
     * @return array{token: string, tenantId: string}
     */
    private function registerTenant(string $email = 'ada@example.test'): array
    {
        $response = $this->postJson('/api/register', [
            'tenant_name' => 'Acme',
            'name' => 'Ada Lovelace',
            'email' => $email,
            'password' => 'correct-horse-battery-staple',
        ])->assertStatus(201);

        $token = $response->json('token');
        $tenantId = $response->json('tenant.id');

        $this->assertIsString($token);
        $this->assertIsString($tenantId);

        return ['token' => $token, 'tenantId' => $tenantId];
    }
}
