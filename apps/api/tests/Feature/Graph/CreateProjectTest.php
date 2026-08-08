<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\RegistersViaHttp;
use Tests\TestCase;

class CreateProjectTest extends TestCase
{
    use DatabaseTransactions;
    use RegistersViaHttp;

    public function test_creates_a_project_for_the_authenticated_tenant(): void
    {
        $registration = $this->registerTenant();

        $response = $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/projects', ['name' => 'Acme Website']);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Acme Website')
            ->assertJsonPath('status', 'active');

        $this->app->make(TenantContext::class)->bind($registration['tenantId']);
        $this->assertDatabaseHas('projects', [
            'id' => $response->json('id'),
            'tenant_id' => $registration['tenantId'],
            'name' => 'Acme Website',
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/projects', ['name' => 'Acme Website'])
            ->assertStatus(401);
    }

    public function test_requires_a_name(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/projects', [])
            ->assertStatus(422);
    }
}
