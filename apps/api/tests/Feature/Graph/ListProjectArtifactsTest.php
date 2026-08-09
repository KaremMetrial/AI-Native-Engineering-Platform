<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListProjectArtifactsTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_artifacts_for_a_project(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/artifacts', ['project_id' => $projectId, 'type' => 'srs', 'content' => 'The system shall...'])
            ->assertStatus(201);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/artifacts', ['project_id' => $projectId, 'type' => 'task', 'content' => 'Build the API.'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/projects/{$projectId}/artifacts");

        $response->assertStatus(200);
        $types = array_column($response->json('artifacts'), 'type');
        $this->assertCount(2, $types);
        $this->assertContains('srs', $types);
        $this->assertContains('task', $types);
    }

    public function test_returns_404_for_an_unknown_project(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/projects/'.((string) Str::uuid()).'/artifacts');

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/projects/'.((string) Str::uuid()).'/artifacts')->assertStatus(401);
    }

    private function createProjectViaHttp(string $token): string
    {
        $project = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', ['name' => 'Acme Website'])
            ->assertStatus(201);

        $projectId = $project->json('id');
        $this->assertIsString($projectId);

        return $projectId;
    }
}
