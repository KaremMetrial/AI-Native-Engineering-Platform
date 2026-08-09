<?php

declare(strict_types=1);

namespace Tests\Feature\Requirements;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class ListRequirementDocumentsTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_lists_documents_for_the_authenticated_tenant(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $projectId = $this->createProjectViaHttp($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/requirement-documents', ['project_id' => $projectId, 'type' => 'brd', 'title' => 'Acme BRD'])
            ->assertStatus(201);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/requirement-documents', ['project_id' => $projectId, 'type' => 'srs', 'title' => 'Acme SRS'])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/requirement-documents');

        $response->assertStatus(200);
        $titles = array_column($response->json('documents'), 'title');
        $this->assertCount(2, $titles);
        $this->assertContains('Acme BRD', $titles);
        $this->assertContains('Acme SRS', $titles);
    }

    public function test_does_not_list_another_tenants_documents(): void
    {
        $otherTenant = $this->registerTenant('owner@other-tenant.test');
        $otherProjectId = $this->createProjectViaHttp($otherTenant['token']);
        $this->withHeader('Authorization', "Bearer {$otherTenant['token']}")
            ->postJson('/api/requirement-documents', ['project_id' => $otherProjectId, 'type' => 'brd', 'title' => 'Other Tenant Document'])
            ->assertStatus(201);

        $ownTenant = $this->registerTenant('owner@own-tenant.test');

        // RequestGuard memoizes the resolved user on the guard instance,
        // and AuthManager caches that guard instance by name -- both
        // persist across these simulated in-process requests within one
        // test, unlike real separate HTTP requests (see LogoutTest).
        Auth::forgetGuards();

        $response = $this->withHeader('Authorization', "Bearer {$ownTenant['token']}")->getJson('/api/requirement-documents');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('documents'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/requirement-documents')->assertStatus(401);
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
