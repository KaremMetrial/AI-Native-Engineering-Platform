<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class GetDiscoverySessionTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_gets_a_session_by_id(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/discovery-sessions', ['project_id' => $projectId, 'title' => 'Acme kickoff'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/discovery-sessions/{$created->json('id')}");

        $response->assertStatus(200)
            ->assertJsonPath('title', 'Acme kickoff')
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('completed_at', null);
    }

    public function test_returns_404_for_an_unknown_session(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/discovery-sessions/'.((string) Str::uuid()));

        $response->assertStatus(404);
    }

    public function test_does_not_get_another_tenants_session(): void
    {
        $otherTenant = $this->registerTenant('owner@other-tenant.test');
        $otherProjectId = $this->createProjectViaHttp($otherTenant['token']);
        $otherSession = $this->withHeader('Authorization', "Bearer {$otherTenant['token']}")
            ->postJson('/api/discovery-sessions', ['project_id' => $otherProjectId, 'title' => 'Other Tenant Session'])
            ->assertStatus(201);

        $ownTenant = $this->registerTenant('owner@own-tenant.test');

        // RequestGuard memoizes the resolved user on the guard instance,
        // and AuthManager caches that guard instance by name -- both
        // persist across these simulated in-process requests within one
        // test, unlike real separate HTTP requests (see LogoutTest).
        Auth::forgetGuards();

        $response = $this->withHeader('Authorization', "Bearer {$ownTenant['token']}")
            ->getJson("/api/discovery-sessions/{$otherSession->json('id')}");

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/discovery-sessions/'.((string) Str::uuid()))->assertStatus(401);
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
