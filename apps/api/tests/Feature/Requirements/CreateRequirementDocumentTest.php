<?php

declare(strict_types=1);

namespace Tests\Feature\Requirements;

use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class CreateRequirementDocumentTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_creates_a_requirement_document_for_the_authenticated_tenant(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/requirement-documents', ['project_id' => $projectId, 'type' => 'brd', 'title' => 'Acme BRD']);

        $response->assertStatus(201)
            ->assertJsonPath('project_id', $projectId)
            ->assertJsonPath('type', 'brd')
            ->assertJsonPath('title', 'Acme BRD')
            ->assertJsonPath('status', 'draft');

        $this->app->make(TenantContext::class)->bind($registration['tenantId']);
        $this->assertDatabaseHas('requirement_documents', [
            'id' => $response->json('id'),
            'tenant_id' => $registration['tenantId'],
            'project_id' => $projectId,
        ]);
    }

    public function test_returns_422_when_the_project_does_not_exist(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/requirement-documents', ['project_id' => (string) Str::uuid(), 'type' => 'brd', 'title' => 'Acme BRD']);

        $response->assertStatus(422);
    }

    public function test_returns_422_for_an_invalid_document_type(): void
    {
        $registration = $this->registerTenant();
        $projectId = $this->createProjectViaHttp($registration['token']);

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/requirement-documents', ['project_id' => $projectId, 'type' => 'not-a-type', 'title' => 'Acme BRD']);

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/requirement-documents', ['project_id' => (string) Str::uuid(), 'type' => 'brd', 'title' => 'Acme BRD'])
            ->assertStatus(401);
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
