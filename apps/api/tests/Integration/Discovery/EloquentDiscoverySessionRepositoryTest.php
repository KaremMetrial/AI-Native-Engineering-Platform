<?php

declare(strict_types=1);

namespace Tests\Integration\Discovery;

use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\SessionStatus;
use App\Discovery\Infrastructure\EloquentDiscoverySessionRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesDiscoveryFixtures;
use Tests\TestCase;

class EloquentDiscoverySessionRepositoryTest extends TestCase
{
    use CreatesDiscoveryFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_a_session(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentDiscoverySessionRepository;
        $session = DiscoverySession::start((string) Str::uuid(), $tenantId, $projectId, 'Acme kickoff', $userId);
        $repository->save($session);

        $found = $repository->findById($session->id);

        $this->assertNotNull($found);
        $this->assertSame('Acme kickoff', $found->title);
        $this->assertSame(SessionStatus::InProgress, $found->status());
        $this->assertNull($found->completedAt());
    }

    public function test_a_second_save_persists_the_completed_state(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();

        $repository = new EloquentDiscoverySessionRepository;
        $session = DiscoverySession::start((string) Str::uuid(), $tenantId, $projectId, 'Acme kickoff', $userId);
        $repository->save($session);

        $session->complete();
        $repository->save($session);

        $found = $repository->findById($session->id);

        $this->assertNotNull($found);
        $this->assertSame(SessionStatus::Completed, $found->status());
        $this->assertNotNull($found->completedAt());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentDiscoverySessionRepository)->findById((string) Str::uuid()));
    }
}
