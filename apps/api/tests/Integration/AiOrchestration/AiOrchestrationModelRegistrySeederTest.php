<?php

declare(strict_types=1);

namespace Tests\Integration\AiOrchestration;

use App\AiOrchestration\Domain\ModelStatus;
use App\AiOrchestration\Infrastructure\EloquentModelRegistryEntry;
use Database\Seeders\AiOrchestrationModelRegistrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AiOrchestrationModelRegistrySeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeds_active_model_entries(): void
    {
        $this->seed(AiOrchestrationModelRegistrySeeder::class);

        $entries = EloquentModelRegistryEntry::query()->get();

        $this->assertGreaterThanOrEqual(4, $entries->count());
        foreach ($entries as $entry) {
            $this->assertSame(ModelStatus::Active->value, $entry->status);
        }
        $this->assertTrue($entries->pluck('model_id')->contains('claude-sonnet-5'));
    }

    public function test_is_idempotent_when_run_twice(): void
    {
        $this->seed(AiOrchestrationModelRegistrySeeder::class);
        $countAfterFirstRun = EloquentModelRegistryEntry::query()->count();

        $this->seed(AiOrchestrationModelRegistrySeeder::class);
        $countAfterSecondRun = EloquentModelRegistryEntry::query()->count();

        $this->assertSame($countAfterFirstRun, $countAfterSecondRun);
    }
}
