<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class CreateArtifactTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_creates_an_artifact_with_its_first_version(): void
    {
        $registration = $this->registerTenant();
        $projectId = $this->createProjectViaHttp($registration['token']);

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/artifacts', [
                'project_id' => $projectId,
                'type' => 'brd',
                'content' => 'Business requirements draft.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('type', 'brd')
            ->assertJsonPath('status', 'active');

        $this->assertIsString($response->json('current_version_id'));
    }

    public function test_returns_422_when_the_project_does_not_exist(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/artifacts', [
                'project_id' => (string) Str::uuid(),
                'type' => 'brd',
                'content' => 'Business requirements draft.',
            ]);

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/artifacts', [
            'project_id' => (string) Str::uuid(),
            'type' => 'brd',
            'content' => 'Business requirements draft.',
        ])->assertStatus(401);
    }

    private function createProjectViaHttp(string $token): string
    {
        /** @var TestResponse $response */
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', ['name' => 'Acme Website'])
            ->assertStatus(201);

        $projectId = $response->json('id');
        $this->assertIsString($projectId);

        return $projectId;
    }
}
