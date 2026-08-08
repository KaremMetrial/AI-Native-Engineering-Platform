<?php

declare(strict_types=1);

namespace Tests\Integration\Discovery;

use App\Discovery\Domain\Question;
use App\Discovery\Domain\Response;
use App\Discovery\Infrastructure\EloquentQuestionRepository;
use App\Discovery\Infrastructure\EloquentResponseRepository;
use App\Tenancy\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesDiscoveryFixtures;
use Tests\TestCase;

class EloquentResponseRepositoryTest extends TestCase
{
    use CreatesDiscoveryFixtures;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_save_then_find_round_trips_a_response(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);
        $projectId = $this->createProject($tenantId);
        $userId = $this->createUser();
        $sessionId = $this->createDiscoverySession($tenantId, $projectId, $userId);

        $question = Question::ask((string) Str::uuid(), $tenantId, $sessionId, 'What problem are you solving?', 1, $userId);
        (new EloquentQuestionRepository)->save($question);

        $repository = new EloquentResponseRepository;
        $response = Response::record((string) Str::uuid(), $tenantId, $question->id, 'We lose bids on turnaround time.', $userId);
        $repository->save($response);

        $found = $repository->findById($response->id);

        $this->assertNotNull($found);
        $this->assertSame($question->id, $found->questionId);
        $this->assertSame('We lose bids on turnaround time.', $found->content);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $tenantId = $this->createTenant();
        $this->app->make(TenantContext::class)->bind($tenantId);

        $this->assertNull((new EloquentResponseRepository)->findById((string) Str::uuid()));
    }
}
