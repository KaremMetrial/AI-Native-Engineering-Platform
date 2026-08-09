<?php

declare(strict_types=1);

namespace Tests\Integration\Discovery;

use App\Discovery\Domain\Assumption;
use App\Discovery\Infrastructure\EloquentAssumptionRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesDiscoveryFixtures;
use Tests\TestCase;

class EloquentAssumptionRepositoryTest extends TestCase
{
    use CreatesDiscoveryFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_an_assumption(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $sessionId = $this->createDiscoverySession($tenantId, $projectId, $userId);

        $repository = new EloquentAssumptionRepository;
        $assumption = Assumption::capture((string) Str::uuid(), $tenantId, $sessionId, 'Stakeholders will respond within 48 hours.', $userId);
        $repository->save($assumption);

        $found = $repository->findById($assumption->id);

        $this->assertNotNull($found);
        $this->assertSame('Stakeholders will respond within 48 hours.', $found->statement);
        $this->assertSame($sessionId, $found->sessionId);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentAssumptionRepository)->findById((string) Str::uuid()));
    }

    public function test_find_all_by_session_returns_only_that_sessions_assumptions(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $sessionA = $this->createDiscoverySession($tenantId, $projectId, $userId);
        $sessionB = $this->createDiscoverySession($tenantId, $projectId, $userId);

        $repository = new EloquentAssumptionRepository;
        $repository->save(Assumption::capture((string) Str::uuid(), $tenantId, $sessionA, 'Assumption A.', $userId));
        $repository->save(Assumption::capture((string) Str::uuid(), $tenantId, $sessionB, 'Assumption B.', $userId));

        $assumptions = $repository->findAllBySession($sessionA);

        $this->assertCount(1, $assumptions);
        $this->assertSame('Assumption A.', $assumptions[0]->statement);
    }
}
