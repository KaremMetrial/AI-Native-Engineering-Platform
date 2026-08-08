<?php

declare(strict_types=1);

namespace Tests\Feature\AiOrchestration;

use App\AiOrchestration\Domain\Provider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesAiOrchestrationFixtures;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class RequestGenerationTest extends TestCase
{
    use CreatesAiOrchestrationFixtures;
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_queues_a_generation_request_and_returns_202(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/generation-requests', $this->requestBody());

        $response->assertStatus(202)
            ->assertJsonPath('status', 'queued');
        $this->assertIsString($response->json('id'));
    }

    public function test_selects_a_model_when_an_active_model_satisfies_the_requirement(): void
    {
        $entry = $this->registerModel(Provider::Anthropic, active: true);
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/generation-requests', $this->requestBody())
            ->assertStatus(202);

        $generationRequestId = $response->json('id');
        $this->assertIsString($generationRequestId);

        $get = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson("/api/generation-requests/{$generationRequestId}");

        $get->assertStatus(200)
            ->assertJsonPath('status', 'selected')
            ->assertJsonPath('selected_model_id', $entry->modelId);
    }

    public function test_fails_cleanly_when_no_active_model_satisfies_the_requirement(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/generation-requests', $this->requestBody())
            ->assertStatus(202);

        $generationRequestId = $response->json('id');
        $this->assertIsString($generationRequestId);

        $get = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson("/api/generation-requests/{$generationRequestId}");

        $get->assertStatus(200)
            ->assertJsonPath('status', 'failed');
        $this->assertIsString($get->json('failure_reason'));
    }

    public function test_requires_a_workflow_name(): void
    {
        $registration = $this->registerTenant();
        $body = $this->requestBody();
        unset($body['workflow_name']);

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/generation-requests', $body)
            ->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/generation-requests', $this->requestBody())
            ->assertStatus(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(): array
    {
        return [
            'workflow_name' => 'brd-synthesis',
            'structured_output' => 'json_mode',
            'tool_use' => 'none',
            'streaming' => 'none',
            'min_context_window' => 1000,
        ];
    }
}
