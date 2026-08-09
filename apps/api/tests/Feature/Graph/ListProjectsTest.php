<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListProjectsTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_projects_for_the_authenticated_tenant(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', ['name' => 'Acme Website'])
            ->assertStatus(201);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', ['name' => 'Acme Mobile'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/projects');

        $response->assertStatus(200);
        $names = array_column($response->json('projects'), 'name');
        $this->assertCount(2, $names);
        $this->assertContains('Acme Website', $names);
        $this->assertContains('Acme Mobile', $names);
    }

    public function test_does_not_list_another_tenants_projects(): void
    {
        $otherTenant = $this->registerTenant('owner@other-tenant.test');
        $this->withHeader('Authorization', "Bearer {$otherTenant['token']}")
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

        $response = $this->withHeader('Authorization', "Bearer {$ownTenant['token']}")->getJson('/api/projects');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('projects'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/projects')->assertStatus(401);
    }
}
