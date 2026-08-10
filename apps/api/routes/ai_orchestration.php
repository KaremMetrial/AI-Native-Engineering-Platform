<?php

declare(strict_types=1);

use App\AiOrchestration\Presentation\Controllers\GetGenerationRequestController;
use App\AiOrchestration\Presentation\Controllers\ListGenerationRequestsController;
use App\AiOrchestration\Presentation\Controllers\RequestGenerationController;
use App\Tenancy\Presentation\BindTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', BindTenantContext::class])->group(function (): void {
    Route::post('/generation-requests', RequestGenerationController::class);
    Route::get('/generation-requests', ListGenerationRequestsController::class);
    Route::get('/generation-requests/{generationRequestId}', GetGenerationRequestController::class);
});
