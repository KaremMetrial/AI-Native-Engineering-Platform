<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class GetProjectTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_gets_a_project_by_id(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', ['name' => 'Acme Website'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/projects/{$created->json('id')}");

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Acme Website')
            ->assertJsonPath('status', 'active');
    }

    public function test_returns_404_for_an_unknown_project(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/projects/'.((string) Str::uuid()));

        $response->assertStatus(404);
    }

    public function test_does_not_get_another_tenants_project(): void
    {
        $otherTenant = $this->registerTenant('owner@other-tenant.test');
        $otherProject = $this->withHeader('Authorization', "Bearer {$otherTenant['token']}")
            ->postJson('/api/projects', ['name' => 'Other Tenant Project'])
            ->assertStatus(201);

        $ownTenant = $this->registerTenant('owner@own-tenant.test');

        // RequestGuard memoizes the resolved user on the guard instance,
        // and AuthManager caches that guard instance by name -- both
        // persist across these simulated in-process requests within one
        // test, unlike real separate HTTP requests (see LogoutTest).
        // Without this, the request below would reuse the other tenant's
        // already-resolved user instead of re-authenticating this token.
        Auth::forgetGuards();

        $response = $this->withHeader('Authorization', "Bearer {$ownTenant['token']}")
            ->getJson("/api/projects/{$otherProject->json('id')}");

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/projects/'.((string) Str::uuid()))->assertStatus(401);
    }
}
