<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class GetArtifactTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_gets_an_artifact_with_its_version_history(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/artifacts', ['project_id' => $projectId, 'type' => 'srs', 'content' => 'v1'])
            ->assertStatus(201);
        $artifactId = $created->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/artifacts/{$artifactId}/versions", ['content' => 'v2'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/artifacts/{$artifactId}");

        $response->assertStatus(200)
            ->assertJsonPath('type', 'srs')
            ->assertJsonCount(2, 'versions');
        $this->assertSame('v1', $response->json('versions.0.content'));
        $this->assertSame('v2', $response->json('versions.1.content'));
    }

    public function test_returns_404_for_an_unknown_artifact(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/artifacts/'.((string) Str::uuid()));

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/artifacts/'.((string) Str::uuid()))->assertStatus(401);
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
