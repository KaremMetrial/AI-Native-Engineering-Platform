<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListQuestionsTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_questions_for_a_session(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $sessionId = $this->createSessionViaHttp($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-sessions/{$sessionId}/questions", ['prompt' => 'What problem are we solving?'])
            ->assertStatus(201);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-sessions/{$sessionId}/questions", ['prompt' => 'Who is the user?'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/discovery-sessions/{$sessionId}/questions");

        $response->assertStatus(200);
        $prompts = array_column($response->json('questions'), 'prompt');
        $this->assertCount(2, $prompts);
        $this->assertContains('What problem are we solving?', $prompts);
        $this->assertContains('Who is the user?', $prompts);
    }

    public function test_returns_404_for_an_unknown_session(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/discovery-sessions/'.((string) Str::uuid()).'/questions');

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/discovery-sessions/'.((string) Str::uuid()).'/questions')->assertStatus(401);
    }

    private function createSessionViaHttp(string $token): string
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
