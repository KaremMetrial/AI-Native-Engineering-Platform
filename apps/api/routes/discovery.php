<?php

declare(strict_types=1);

use App\Discovery\Presentation\Controllers\AddQuestionController;
use App\Discovery\Presentation\Controllers\CaptureAssumptionController;
use App\Discovery\Presentation\Controllers\CaptureConstraintController;
use App\Discovery\Presentation\Controllers\CompleteDiscoverySessionController;
use App\Discovery\Presentation\Controllers\GetDiscoverySessionController;
use App\Discovery\Presentation\Controllers\GetQuestionController;
use App\Discovery\Presentation\Controllers\ListAssumptionsController;
use App\Discovery\Presentation\Controllers\ListConstraintsController;
use App\Discovery\Presentation\Controllers\ListDiscoverySessionsController;
use App\Discovery\Presentation\Controllers\ListQuestionsController;
use App\Discovery\Presentation\Controllers\ListResponsesController;
use App\Discovery\Presentation\Controllers\RecordResponseController;
use App\Discovery\Presentation\Controllers\StartDiscoverySessionController;
use App\Tenancy\Presentation\BindTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', BindTenantContext::class])->group(function (): void {
    Route::post('/discovery-sessions', StartDiscoverySessionController::class);
    Route::get('/discovery-sessions', ListDiscoverySessionsController::class);
    Route::get('/discovery-sessions/{sessionId}', GetDiscoverySessionController::class);
    Route::post('/discovery-sessions/{sessionId}/questions', AddQuestionController::class);
    Route::get('/discovery-sessions/{sessionId}/questions', ListQuestionsController::class);
    Route::get('/discovery-questions/{questionId}', GetQuestionController::class);
    Route::post('/discovery-questions/{questionId}/responses', RecordResponseController::class);
    Route::get('/discovery-questions/{questionId}/responses', ListResponsesController::class);
    Route::post('/discovery-sessions/{sessionId}/assumptions', CaptureAssumptionController::class);
    Route::get('/discovery-sessions/{sessionId}/assumptions', ListAssumptionsController::class);
    Route::post('/discovery-sessions/{sessionId}/constraints', CaptureConstraintController::class);
    Route::get('/discovery-sessions/{sessionId}/constraints', ListConstraintsController::class);
    Route::post('/discovery-sessions/{sessionId}/complete', CompleteDiscoverySessionController::class);
});
