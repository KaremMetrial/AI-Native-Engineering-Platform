<?php

declare(strict_types=1);

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class GetGenerationRequestTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_returns_404_for_an_unknown_generation_request(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/generation-requests/'.((string) Str::uuid()));

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/generation-requests/'.((string) Str::uuid()))
            ->assertStatus(401);
    }
}
