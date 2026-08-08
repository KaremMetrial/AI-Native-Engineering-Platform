<?php

declare(strict_types=1);

use App\Discovery\Presentation\Controllers\AddQuestionController;
use App\Discovery\Presentation\Controllers\CaptureAssumptionController;
use App\Discovery\Presentation\Controllers\CaptureConstraintController;
use App\Discovery\Presentation\Controllers\CompleteDiscoverySessionController;
use App\Discovery\Presentation\Controllers\RecordResponseController;
use App\Discovery\Presentation\Controllers\StartDiscoverySessionController;
use App\Tenancy\Presentation\BindTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', BindTenantContext::class])->group(function (): void {
    Route::post('/discovery-sessions', StartDiscoverySessionController::class);
    Route::post('/discovery-sessions/{sessionId}/questions', AddQuestionController::class);
    Route::post('/discovery-questions/{questionId}/responses', RecordResponseController::class);
    Route::post('/discovery-sessions/{sessionId}/assumptions', CaptureAssumptionController::class);
    Route::post('/discovery-sessions/{sessionId}/constraints', CaptureConstraintController::class);
    Route::post('/discovery-sessions/{sessionId}/complete', CompleteDiscoverySessionController::class);
});
