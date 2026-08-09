<?php

declare(strict_types=1);

namespace Tests\Integration\Discovery;

use App\Discovery\Domain\Constraint;
use App\Discovery\Infrastructure\EloquentConstraintRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesDiscoveryFixtures;
use Tests\TestCase;

class EloquentConstraintRepositoryTest extends TestCase
{
    use CreatesDiscoveryFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_a_constraint(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $sessionId = $this->createDiscoverySession($tenantId, $projectId, $userId);

        $repository = new EloquentConstraintRepository;
        $constraint = Constraint::capture((string) Str::uuid(), $tenantId, $sessionId, 'Must integrate with the existing Jira instance.', $userId);
        $repository->save($constraint);

        $found = $repository->findById($constraint->id);

        $this->assertNotNull($found);
        $this->assertSame('Must integrate with the existing Jira instance.', $found->statement);
        $this->assertSame($sessionId, $found->sessionId);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentConstraintRepository)->findById((string) Str::uuid()));
    }

    public function test_find_all_by_session_returns_only_that_sessions_constraints(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $sessionA = $this->createDiscoverySession($tenantId, $projectId, $userId);
        $sessionB = $this->createDiscoverySession($tenantId, $projectId, $userId);

        $repository = new EloquentConstraintRepository;
        $repository->save(Constraint::capture((string) Str::uuid(), $tenantId, $sessionA, 'Constraint A.', $userId));
        $repository->save(Constraint::capture((string) Str::uuid(), $tenantId, $sessionB, 'Constraint B.', $userId));

        $constraints = $repository->findAllBySession($sessionA);

        $this->assertCount(1, $constraints);
        $this->assertSame('Constraint A.', $constraints[0]->statement);
    }
}
