<?php

use App\AiOrchestration\Infrastructure\AiOrchestrationServiceProvider;
use App\Discovery\Infrastructure\DiscoveryServiceProvider;
use App\Graph\Infrastructure\GraphServiceProvider;
use App\Identity\Infrastructure\IdentityServiceProvider;
use App\Providers\AppServiceProvider;
use App\Requirements\Infrastructure\RequirementsServiceProvider;
use App\Tenancy\Infrastructure\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    IdentityServiceProvider::class,
    GraphServiceProvider::class,
    DiscoveryServiceProvider::class,
    RequirementsServiceProvider::class,
    AiOrchestrationServiceProvider::class,
];
