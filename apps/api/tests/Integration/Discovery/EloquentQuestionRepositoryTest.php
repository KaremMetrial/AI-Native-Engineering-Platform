<?php

declare(strict_types=1);

namespace Tests\Integration\Discovery;

use App\Discovery\Domain\Question;
use App\Discovery\Infrastructure\EloquentQuestionRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesDiscoveryFixtures;
use Tests\TestCase;

class EloquentQuestionRepositoryTest extends TestCase
{
    use CreatesDiscoveryFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_a_question(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $sessionId = $this->createDiscoverySession($tenantId, $projectId, $userId);

        $repository = new EloquentQuestionRepository;
        $question = Question::ask((string) Str::uuid(), $tenantId, $sessionId, 'What problem are you solving?', 1, $userId);
        $repository->save($question);

        $found = $repository->findById($question->id);

        $this->assertNotNull($found);
        $this->assertSame('What problem are you solving?', $found->prompt);
        $this->assertSame(1, $found->sequence);
    }

    public function test_count_by_session_counts_only_that_sessions_questions(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $sessionA = $this->createDiscoverySession($tenantId, $projectId, $userId);
        $sessionB = $this->createDiscoverySession($tenantId, $projectId, $userId);

        $repository = new EloquentQuestionRepository;
        $repository->save(Question::ask((string) Str::uuid(), $tenantId, $sessionA, 'First?', 1, $userId));
        $repository->save(Question::ask((string) Str::uuid(), $tenantId, $sessionA, 'Second?', 2, $userId));
        $repository->save(Question::ask((string) Str::uuid(), $tenantId, $sessionB, 'Other session?', 1, $userId));

        $this->assertSame(2, $repository->countBySession($sessionA));
        $this->assertSame(1, $repository->countBySession($sessionB));
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentQuestionRepository)->findById((string) Str::uuid()));
    }
}
