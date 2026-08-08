<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class LinkArtifactVersionsTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_links_two_artifact_versions(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);
        $requirementVersionId = $this->createArtifactViaHttp($token, $projectId, 'srs', 'The system shall...');
        $taskVersionId = $this->createArtifactViaHttp($token, $projectId, 'task', 'Implement the login form.');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/artifact-links', [
                'from_version_id' => $taskVersionId,
                'to_version_id' => $requirementVersionId,
                'link_type' => 'implements',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('from_version_id', $taskVersionId)
            ->assertJsonPath('to_version_id', $requirementVersionId)
            ->assertJsonPath('link_type', 'implements');
    }

    public function test_returns_422_when_the_source_version_does_not_exist(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);
        $requirementVersionId = $this->createArtifactViaHttp($token, $projectId, 'srs', 'The system shall...');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/artifact-links', [
                'from_version_id' => (string) Str::uuid(),
                'to_version_id' => $requirementVersionId,
                'link_type' => 'implements',
            ]);

        $response->assertStatus(422);
    }

    public function test_returns_422_when_the_target_version_does_not_exist(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);
        $taskVersionId = $this->createArtifactViaHttp($token, $projectId, 'task', 'Implement the login form.');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/artifact-links', [
                'from_version_id' => $taskVersionId,
                'to_version_id' => (string) Str::uuid(),
                'link_type' => 'implements',
            ]);

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/artifact-links', [
            'from_version_id' => (string) Str::uuid(),
            'to_version_id' => (string) Str::uuid(),
            'link_type' => 'implements',
        ])->assertStatus(401);
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

    private function createArtifactViaHttp(string $token, string $projectId, string $type, string $content): string
    {
        $artifact = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/artifacts', [
                'project_id' => $projectId,
                'type' => $type,
                'content' => $content,
            ])->assertStatus(201);

        $versionId = $artifact->json('current_version_id');
        $this->assertIsString($versionId);

        return $versionId;
    }
}
