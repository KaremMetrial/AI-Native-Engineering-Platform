<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListResponsesTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_responses_for_a_question(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $questionId = $this->createQuestionViaHttp($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-questions/{$questionId}/responses", ['content' => 'Stakeholder A answer.'])
            ->assertStatus(201);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-questions/{$questionId}/responses", ['content' => 'Stakeholder B answer.'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/discovery-questions/{$questionId}/responses");

        $response->assertStatus(200);
        $contents = array_column($response->json('responses'), 'content');
        $this->assertCount(2, $contents);
        $this->assertContains('Stakeholder A answer.', $contents);
        $this->assertContains('Stakeholder B answer.', $contents);
    }

    public function test_returns_404_for_an_unknown_question(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/discovery-questions/'.((string) Str::uuid()).'/responses');

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/discovery-questions/'.((string) Str::uuid()).'/responses')->assertStatus(401);
    }

    private function createQuestionViaHttp(string $token): string
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

        $question = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-sessions/{$sessionId}/questions", ['prompt' => 'What problem are we solving?'])
            ->assertStatus(201);
        $questionId = $question->json('id');
        $this->assertIsString($questionId);

        return $questionId;
    }
}
