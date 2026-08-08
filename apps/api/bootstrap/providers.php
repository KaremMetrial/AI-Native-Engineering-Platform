<?php

use App\Graph\Infrastructure\GraphServiceProvider;
use App\Identity\Infrastructure\IdentityServiceProvider;
use App\Providers\AppServiceProvider;
use App\Tenancy\Infrastructure\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    IdentityServiceProvider::class,
    GraphServiceProvider::class,
];
