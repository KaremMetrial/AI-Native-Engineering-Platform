<?php

declare(strict_types=1);

namespace Tests\Integration\AiOrchestration;

use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Domain\TenantProviderPolicy;
use App\AiOrchestration\Infrastructure\EloquentTenantProviderPolicyRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesAiOrchestrationFixtures;
use Tests\TestCase;

class EloquentTenantProviderPolicyRepositoryTest extends TestCase
{
    use CreatesAiOrchestrationFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_a_policy(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $repository = new EloquentTenantProviderPolicyRepository;
        $policy = TenantProviderPolicy::restrict($tenantId, [Provider::Anthropic, Provider::Google]);
        $repository->save($policy);

        $found = $repository->findByTenant($tenantId);

        $this->assertNotNull($found);
        $this->assertTrue($found->allows(Provider::Anthropic));
        $this->assertTrue($found->allows(Provider::Google));
        $this->assertFalse($found->allows(Provider::OpenAi));
    }

    public function test_find_by_tenant_returns_null_when_no_policy_exists(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentTenantProviderPolicyRepository)->findByTenant($tenantId));
    }
}
