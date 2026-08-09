<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class GetQuestionTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_gets_a_question_by_id(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $sessionId = $this->createSessionViaHttp($token);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-sessions/{$sessionId}/questions", ['prompt' => 'What problem are we solving?'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/discovery-questions/{$created->json('id')}");

        $response->assertStatus(200)
            ->assertJsonPath('prompt', 'What problem are we solving?')
            ->assertJsonPath('session_id', $sessionId)
            ->assertJsonPath('sequence', 1);
    }

    public function test_returns_404_for_an_unknown_question(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/discovery-questions/'.((string) Str::uuid()));

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/discovery-questions/'.((string) Str::uuid()))->assertStatus(401);
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
