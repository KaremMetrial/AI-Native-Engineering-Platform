<?php

declare(strict_types=1);

namespace App\Requirements\Infrastructure;

use App\Requirements\Domain\RequirementDocumentRepository;
use App\Requirements\Domain\RequirementRepository;
use Illuminate\Support\ServiceProvider;

class RequirementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RequirementDocumentRepository::class, EloquentRequirementDocumentRepository::class);
        $this->app->bind(RequirementRepository::class, EloquentRequirementRepository::class);
    }
}
