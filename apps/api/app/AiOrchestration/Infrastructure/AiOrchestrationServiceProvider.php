<?php

declare(strict_types=1);

namespace App\AiOrchestration\Infrastructure;

use App\AiOrchestration\Domain\GenerationDispatcher;
use App\AiOrchestration\Domain\GenerationRecordRepository;
use App\AiOrchestration\Domain\ModelAdapter;
use App\AiOrchestration\Domain\ModelRegistryRepository;
use App\AiOrchestration\Domain\TenantProviderPolicyRepository;
use Illuminate\Support\ServiceProvider;

class AiOrchestrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ModelRegistryRepository::class, EloquentModelRegistryRepository::class);
        $this->app->bind(TenantProviderPolicyRepository::class, EloquentTenantProviderPolicyRepository::class);
        $this->app->bind(GenerationRecordRepository::class, EloquentGenerationRecordRepository::class);
        $this->app->bind(ModelAdapter::class, NullModelAdapter::class);
        $this->app->bind(GenerationDispatcher::class, QueuedGenerationDispatcher::class);
    }
}
