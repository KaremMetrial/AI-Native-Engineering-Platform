<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListApprovalsTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_approvals_for_a_version(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $versionId = $this->createArtifactViaHttp($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/artifact-versions/{$versionId}/approvals", ['decision' => 'approved', 'comment' => 'Looks good.'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/artifact-versions/{$versionId}/approvals");

        $response->assertStatus(200)->assertJsonCount(1, 'approvals');
        $this->assertSame('approved', $response->json('approvals.0.decision'));
        $this->assertSame('Looks good.', $response->json('approvals.0.comment'));
    }

    public function test_returns_404_for_an_unknown_version(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/artifact-versions/'.((string) Str::uuid()).'/approvals');

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/artifact-versions/'.((string) Str::uuid()).'/approvals')->assertStatus(401);
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
