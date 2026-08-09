<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListArtifactVersionLinksTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_outgoing_and_incoming_links_for_a_version(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);

        $requirementVersionId = $this->createArtifactViaHttp($token, $projectId, 'srs', 'The system shall...');
        $taskVersionId = $this->createArtifactViaHttp($token, $projectId, 'task', 'Build the API.');
        $testVersionId = $this->createArtifactViaHttp($token, $projectId, 'test', 'Verify login works.');

        $this->linkViaHttp($token, $taskVersionId, $requirementVersionId, 'implements');
        $this->linkViaHttp($token, $testVersionId, $taskVersionId, 'verifies');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/artifact-versions/{$taskVersionId}/links");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('outgoing'));
        $this->assertSame($requirementVersionId, $response->json('outgoing.0.to_version_id'));
        $this->assertCount(1, $response->json('incoming'));
        $this->assertSame($testVersionId, $response->json('incoming.0.from_version_id'));
    }

    public function test_returns_404_for_an_unknown_version(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/artifact-versions/'.((string) Str::uuid()).'/links');

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/artifact-versions/'.((string) Str::uuid()).'/links')->assertStatus(401);
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

    private function linkViaHttp(string $token, string $fromVersionId, string $toVersionId, string $linkType): void
    {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/artifact-links', [
                'from_version_id' => $fromVersionId,
                'to_version_id' => $toVersionId,
                'link_type' => $linkType,
            ])->assertStatus(201);
    }
}
