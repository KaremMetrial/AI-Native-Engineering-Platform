<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class CreateArtifactVersionTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_creates_a_new_version_for_an_existing_artifact(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $artifactId = $this->createArtifactViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/artifacts/{$artifactId}/versions", ['content' => 'Revised draft.']);

        $response->assertStatus(201)
            ->assertJsonPath('artifact_id', $artifactId)
            ->assertJsonPath('version_number', 2);
    }

    public function test_returns_404_when_the_artifact_does_not_exist(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/artifacts/'.((string) Str::uuid()).'/versions', ['content' => 'Revised draft.']);

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/artifacts/'.((string) Str::uuid()).'/versions', ['content' => 'Revised draft.'])
            ->assertStatus(401);
    }

    private function createArtifactViaHttp(string $token): string
    {
        $project = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', ['name' => 'Acme Website'])
            ->assertStatus(201);

        $projectId = $project->json('id');
        $this->assertIsString($projectId);

        $artifact = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/artifacts', [
                'project_id' => $projectId,
                'type' => 'brd',
                'content' => 'Business requirements draft.',
            ])->assertStatus(201);

        $artifactId = $artifact->json('id');
        $this->assertIsString($artifactId);

        return $artifactId;
    }
}
