<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ApproveArtifactVersionTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_approves_an_artifact_version(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $versionId = $this->createArtifactViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/artifact-versions/{$versionId}/approvals", [
                'decision' => 'approved',
                'comment' => 'Looks good.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('artifact_version_id', $versionId)
            ->assertJsonPath('decision', 'approved');
    }

    public function test_rejects_an_artifact_version(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $versionId = $this->createArtifactViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/artifact-versions/{$versionId}/approvals", ['decision' => 'rejected']);

        $response->assertStatus(201)
            ->assertJsonPath('decision', 'rejected');
    }

    public function test_returns_404_for_an_unknown_version(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/artifact-versions/'.((string) Str::uuid()).'/approvals', ['decision' => 'approved']);

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/artifact-versions/'.((string) Str::uuid()).'/approvals', ['decision' => 'approved'])
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

        $versionId = $artifact->json('current_version_id');
        $this->assertIsString($versionId);

        return $versionId;
    }
}
