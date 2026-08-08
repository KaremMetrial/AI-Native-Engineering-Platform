<?php

declare(strict_types=1);

namespace Tests\Feature\Requirements;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ApproveRequirementTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_approves_a_requirement(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $requirementId = $this->addRequirementViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirements/{$requirementId}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('id', $requirementId)
            ->assertJsonPath('status', 'approved');
    }

    public function test_returns_404_for_an_unknown_requirement(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/requirements/'.((string) Str::uuid()).'/approve');

        $response->assertStatus(404);
    }

    public function test_returns_422_when_already_approved(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $requirementId = $this->addRequirementViaHttp($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirements/{$requirementId}/approve")
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirements/{$requirementId}/approve");

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/requirements/'.((string) Str::uuid()).'/approve')
            ->assertStatus(401);
    }

    private function addRequirementViaHttp(string $token): string
    {
        $project = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', ['name' => 'Acme Website'])
            ->assertStatus(201);
        $projectId = $project->json('id');
        $this->assertIsString($projectId);

        $document = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/requirement-documents', ['project_id' => $projectId, 'type' => 'brd', 'title' => 'Acme BRD'])
            ->assertStatus(201);
        $documentId = $document->json('id');
        $this->assertIsString($documentId);

        $requirement = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirement-documents/{$documentId}/requirements", ['text' => 'The system shall...'])
            ->assertStatus(201);
        $requirementId = $requirement->json('id');
        $this->assertIsString($requirementId);

        return $requirementId;
    }
}
