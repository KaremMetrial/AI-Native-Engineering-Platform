<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class CompleteDiscoverySessionTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_completes_a_session(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $sessionId = $this->startSessionViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-sessions/{$sessionId}/complete");

        $response->assertStatus(200)
            ->assertJsonPath('id', $sessionId)
            ->assertJsonPath('status', 'completed');
    }

    public function test_returns_404_for_an_unknown_session(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/discovery-sessions/'.((string) Str::uuid()).'/complete');

        $response->assertStatus(404);
    }

    public function test_returns_422_when_the_session_is_already_completed(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $sessionId = $this->startSessionViaHttp($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-sessions/{$sessionId}/complete")
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-sessions/{$sessionId}/complete");

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/discovery-sessions/'.((string) Str::uuid()).'/complete')
            ->assertStatus(401);
    }

    private function startSessionViaHttp(string $token): string
    {
        $project = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', ['name' => 'Acme Website'])
            ->assertStatus(201);
        $projectId = $project->json('id');
        $this->assertIsString($projectId);

        $session = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/discovery-sessions', ['project_id' => $projectId, 'title' => 'Acme kickoff'])
            ->assertStatus(201);
        $sessionId = $session->json('id');
        $this->assertIsString($sessionId);

        return $sessionId;
    }
}
