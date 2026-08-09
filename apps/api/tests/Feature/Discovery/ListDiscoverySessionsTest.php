<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListDiscoverySessionsTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_sessions_for_the_authenticated_tenant(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/discovery-sessions', ['project_id' => $projectId, 'title' => 'Acme kickoff'])
            ->assertStatus(201);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/discovery-sessions', ['project_id' => $projectId, 'title' => 'Acme follow-up'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/discovery-sessions');

        $response->assertStatus(200);
        $titles = array_column($response->json('sessions'), 'title');
        $this->assertCount(2, $titles);
        $this->assertContains('Acme kickoff', $titles);
        $this->assertContains('Acme follow-up', $titles);
    }

    public function test_does_not_list_another_tenants_sessions(): void
    {
        $otherTenant = $this->registerTenant('owner@other-tenant.test');
        $otherProjectId = $this->createProjectViaHttp($otherTenant['token']);
        $this->withHeader('Authorization', "Bearer {$otherTenant['token']}")
            ->postJson('/api/discovery-sessions', ['project_id' => $otherProjectId, 'title' => 'Other Tenant Session'])
            ->assertStatus(201);

        $ownTenant = $this->registerTenant('owner@own-tenant.test');

        // RequestGuard memoizes the resolved user on the guard instance,
        // and AuthManager caches that guard instance by name -- both
        // persist across these simulated in-process requests within one
        // test, unlike real separate HTTP requests (see LogoutTest).
        Auth::forgetGuards();

        $response = $this->withHeader('Authorization', "Bearer {$ownTenant['token']}")->getJson('/api/discovery-sessions');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('sessions'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/discovery-sessions')->assertStatus(401);
    }

    private function createProjectViaHttp(string $token): string
    {
        $project = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', ['name' => 'Acme Website'])
            ->assertStatus(201);

        $projectId = $project->json('id');
        $this->assertIsString($projectId);

        return $projectId;
    }
}
