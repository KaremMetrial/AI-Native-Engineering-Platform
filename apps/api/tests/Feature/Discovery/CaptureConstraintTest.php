<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class CaptureConstraintTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_captures_a_constraint_for_a_session(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $sessionId = $this->startSessionViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-sessions/{$sessionId}/constraints", ['statement' => 'Must integrate with the existing Jira instance.']);

        $response->assertStatus(201)
            ->assertJsonPath('session_id', $sessionId)
            ->assertJsonPath('statement', 'Must integrate with the existing Jira instance.');
    }

    public function test_returns_422_when_the_session_does_not_exist(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/discovery-sessions/'.((string) Str::uuid()).'/constraints', ['statement' => 'Must integrate with the existing Jira instance.']);

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/discovery-sessions/'.((string) Str::uuid()).'/constraints', ['statement' => 'Must integrate with the existing Jira instance.'])
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
