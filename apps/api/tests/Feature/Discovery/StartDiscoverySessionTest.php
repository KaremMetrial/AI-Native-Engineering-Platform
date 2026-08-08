<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class StartDiscoverySessionTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_starts_a_discovery_session_for_the_authenticated_tenant(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/discovery-sessions', ['project_id' => $projectId, 'title' => 'Acme kickoff']);

        $response->assertStatus(201)
            ->assertJsonPath('project_id', $projectId)
            ->assertJsonPath('title', 'Acme kickoff')
            ->assertJsonPath('status', 'in_progress');

        $this->app->make(TenantContext::class)->bind($registration['tenantId']);
        $this->assertDatabaseHas('discovery_sessions', [
            'id' => $response->json('id'),
            'tenant_id' => $registration['tenantId'],
            'project_id' => $projectId,
        ]);
    }

    public function test_returns_422_when_the_project_does_not_exist(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/discovery-sessions', ['project_id' => (string) Str::uuid(), 'title' => 'Acme kickoff']);

        $response->assertStatus(422);
    }

    public function test_requires_a_title(): void
    {
        $registration = $this->registerTenant();
        $projectId = $this->createProjectViaHttp($registration['token']);

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/discovery-sessions', ['project_id' => $projectId])
            ->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/discovery-sessions', ['project_id' => (string) Str::uuid(), 'title' => 'Acme kickoff'])
            ->assertStatus(401);
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
