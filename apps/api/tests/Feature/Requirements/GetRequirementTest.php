<?php

declare(strict_types=1);

namespace Tests\Feature\Requirements;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class GetRequirementTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_gets_a_requirement_by_id(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $documentId = $this->createDocumentViaHttp($token);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirement-documents/{$documentId}/requirements", [
                'text' => 'The system shall allow login via email and password.',
                'acceptance_criteria' => ['Given valid credentials, the user is logged in.'],
            ])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/requirements/{$created->json('id')}");

        $response->assertStatus(200)
            ->assertJsonPath('text', 'The system shall allow login via email and password.')
            ->assertJsonPath('document_id', $documentId)
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('acceptance_criteria.0', 'Given valid credentials, the user is logged in.');
    }

    public function test_returns_404_for_an_unknown_requirement(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/requirements/'.((string) Str::uuid()));

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/requirements/'.((string) Str::uuid()))->assertStatus(401);
    }

    private function createDocumentViaHttp(string $token): string
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

        return $documentId;
    }
}
