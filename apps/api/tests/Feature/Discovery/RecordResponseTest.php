<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class RecordResponseTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_records_a_response_to_a_question(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $questionId = $this->askQuestionViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/discovery-questions/{$questionId}/responses", ['content' => 'We lose bids on turnaround time.']);

        $response->assertStatus(201)
            ->assertJsonPath('question_id', $questionId)
            ->assertJsonPath('content', 'We lose bids on turnaround time.');
    }

    public function test_returns_404_when_the_question_does_not_exist(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/discovery-questions/'.((string) Str::uuid()).'/responses', ['content' => 'We lose bids on turnaround time.']);

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/discovery-questions/'.((string) Str::uuid()).'/responses', ['content' => 'We lose bids on turnaround time.'])
            ->assertStatus(401);
    }

    private function askQuestionViaHttp(string $token): string
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
            ->postJson("/api/discovery-sessions/{$sessionId}/questions", ['prompt' => 'What problem are you solving?'])
            ->assertStatus(201);
        $questionId = $question->json('id');
        $this->assertIsString($questionId);

        return $questionId;
    }
}
