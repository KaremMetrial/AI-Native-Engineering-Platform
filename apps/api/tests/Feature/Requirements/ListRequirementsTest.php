<?php

declare(strict_types=1);

namespace Tests\Feature\Requirements;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListRequirementsTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_requirements_for_a_document_with_acceptance_criteria(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $documentId = $this->createDocumentViaHttp($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/requirement-documents/{$documentId}/requirements", [
                'text' => 'The system shall allow login via email and password.',
                'acceptance_criteria' => ['Given valid credentials, the user is logged in.'],
            ])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/requirement-documents/{$documentId}/requirements");

        $response->assertStatus(200)->assertJsonCount(1, 'requirements');
        $this->assertSame('The system shall allow login via email and password.', $response->json('requirements.0.text'));
        $this->assertSame(['Given valid credentials, the user is logged in.'], $response->json('requirements.0.acceptance_criteria'));
    }

    public function test_returns_404_for_an_unknown_document(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/requirement-documents/'.((string) Str::uuid()).'/requirements');

        $response->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/requirement-documents/'.((string) Str::uuid()).'/requirements')->assertStatus(401);
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
