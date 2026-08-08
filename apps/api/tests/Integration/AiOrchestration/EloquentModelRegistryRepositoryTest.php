<?php

declare(strict_types=1);

namespace Tests\Integration\AiOrchestration;

use App\AiOrchestration\Domain\ModelRegistryEntry;
use App\AiOrchestration\Domain\ModelStatus;
use App\AiOrchestration\Domain\Provider;
use App\AiOrchestration\Infrastructure\EloquentModelRegistryRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\CreatesAiOrchestrationFixtures;
use Tests\TestCase;

class EloquentModelRegistryRepositoryTest extends TestCase
{
    use CreatesAiOrchestrationFixtures;
    use DatabaseTransactions;

    public function test_save_then_find_round_trips_an_entry(): void
    {
        $entry = $this->registerModel(Provider::Anthropic, active: false);

        $found = (new EloquentModelRegistryRepository)->findById($entry->id);

        $this->assertNotNull($found);
        $this->assertSame($entry->modelId, $found->modelId);
        $this->assertSame(Provider::Anthropic, $found->provider);
        $this->assertSame(ModelStatus::Trial, $found->status());
        $this->assertTrue($found->capabilities->vision);
        $this->assertSame(3.0, $found->pricing->inputPerMillionTokensUsd);
    }

    public function test_a_second_save_persists_the_promoted_status(): void
    {
        $entry = $this->registerModel(Provider::Anthropic, active: false);
        $repository = new EloquentModelRegistryRepository;

        $entry->promote();
        $repository->save($entry);

        $found = $repository->findById($entry->id);

        $this->assertNotNull($found);
        $this->assertSame(ModelStatus::Active, $found->status());
    }

    public function test_find_by_status_returns_only_matching_entries(): void
    {
        $active = $this->registerModel(Provider::Anthropic, active: true);
        $this->registerModel(Provider::OpenAi, active: false);

        $found = (new EloquentModelRegistryRepository)->findByStatus(ModelStatus::Active);

        $ids = array_map(static fn (ModelRegistryEntry $entry): string => $entry->id, $found);
        $this->assertContains($active->id, $ids);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $this->assertNull((new EloquentModelRegistryRepository)->findById((string) Str::uuid()));
    }
}
