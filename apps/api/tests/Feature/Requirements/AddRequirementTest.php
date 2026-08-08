<?php

declare(strict_types=1);

namespace Tests\Feature\Requirements;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class AddRequirementTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_adds_a_requirement_with_acceptance_criteria(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $documentId = $this->createDocumentViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirement-documents/{$documentId}/requirements", [
                'text' => 'The system shall allow login via email and password.',
                'acceptance_criteria' => ['Given valid credentials, the user is logged in.'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('document_id', $documentId)
            ->assertJsonPath('text', 'The system shall allow login via email and password.')
            ->assertJsonPath('status', 'draft');
    }

    public function test_returns_422_when_the_document_does_not_exist(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/requirement-documents/'.((string) Str::uuid()).'/requirements', ['text' => 'The system shall...']);

        $response->assertStatus(422);
    }

    public function test_returns_422_when_the_document_is_already_approved(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $documentId = $this->createDocumentViaHttp($token);

        $requirement = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirement-documents/{$documentId}/requirements", ['text' => 'First requirement.'])
            ->assertStatus(201);
        $requirementId = $requirement->json('id');
        $this->assertIsString($requirementId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirements/{$requirementId}/approve")
            ->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirement-documents/{$documentId}/approve")
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirement-documents/{$documentId}/requirements", ['text' => 'Second requirement.']);

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/requirement-documents/'.((string) Str::uuid()).'/requirements', ['text' => 'The system shall...'])
            ->assertStatus(401);
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
