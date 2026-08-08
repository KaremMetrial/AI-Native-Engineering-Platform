<?php

declare(strict_types=1);

namespace App\Discovery\Infrastructure;

use App\Discovery\Domain\AssumptionRepository;
use App\Discovery\Domain\ConstraintRepository;
use App\Discovery\Domain\DiscoverySessionRepository;
use App\Discovery\Domain\QuestionRepository;
use App\Discovery\Domain\ResponseRepository;
use Illuminate\Support\ServiceProvider;

class DiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DiscoverySessionRepository::class, EloquentDiscoverySessionRepository::class);
        $this->app->bind(QuestionRepository::class, EloquentQuestionRepository::class);
        $this->app->bind(ResponseRepository::class, EloquentResponseRepository::class);
        $this->app->bind(AssumptionRepository::class, EloquentAssumptionRepository::class);
        $this->app->bind(ConstraintRepository::class, EloquentConstraintRepository::class);
    }
}
