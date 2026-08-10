<?php

declare(strict_types=1);

namespace Tests\Feature\AiOrchestration;

use App\AiOrchestration\Domain\Provider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\Support\CreatesAiOrchestrationFixtures;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListGenerationRequestsTest extends TestCase
{
    use CreatesAiOrchestrationFixtures;
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_generation_requests_for_the_authenticated_tenant(): void
    {
        $this->registerModel(Provider::Anthropic, active: true);
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/generation-requests', $this->requestBody())
            ->assertStatus(202);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/generation-requests');

        $response->assertStatus(200);
        $requests = $response->json('generation_requests');
        $this->assertCount(1, $requests);
        $this->assertSame('brd-synthesis', $requests[0]['workflow_name']);
        $this->assertSame('selected', $requests[0]['status']);
    }

    public function test_returns_an_empty_list_when_no_requests_exist(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/generation-requests');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('generation_requests'));
    }

    public function test_does_not_list_another_tenants_generation_requests(): void
    {
        $otherTenant = $this->registerTenant('owner@other-tenant.test');
        $this->withHeader('Authorization', "Bearer {$otherTenant['token']}")
            ->postJson('/api/generation-requests', $this->requestBody())
            ->assertStatus(202);

        $ownTenant = $this->registerTenant('owner@own-tenant.test');

        // RequestGuard memoizes the resolved user on the guard instance,
        // and AuthManager caches that guard instance by name -- both
        // persist across these simulated in-process requests within one
        // test, unlike real separate HTTP requests (see LogoutTest).
        Auth::forgetGuards();

        $response = $this->withHeader('Authorization', "Bearer {$ownTenant['token']}")
            ->getJson('/api/generation-requests');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('generation_requests'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/generation-requests')->assertStatus(401);
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
