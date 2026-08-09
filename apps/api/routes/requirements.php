<?php

declare(strict_types=1);

use App\Requirements\Presentation\Controllers\AddRequirementController;
use App\Requirements\Presentation\Controllers\ApproveRequirementController;
use App\Requirements\Presentation\Controllers\ApproveRequirementDocumentController;
use App\Requirements\Presentation\Controllers\CreateRequirementDocumentController;
use App\Requirements\Presentation\Controllers\GetRequirementController;
use App\Requirements\Presentation\Controllers\GetRequirementDocumentController;
use App\Requirements\Presentation\Controllers\ListRequirementDocumentsController;
use App\Requirements\Presentation\Controllers\ListRequirementsController;
use App\Tenancy\Presentation\BindTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', BindTenantContext::class])->group(function (): void {
    Route::post('/requirement-documents', CreateRequirementDocumentController::class);
    Route::get('/requirement-documents', ListRequirementDocumentsController::class);
    Route::get('/requirement-documents/{documentId}', GetRequirementDocumentController::class);
    Route::post('/requirement-documents/{documentId}/requirements', AddRequirementController::class);
    Route::get('/requirement-documents/{documentId}/requirements', ListRequirementsController::class);
    Route::post('/requirement-documents/{documentId}/approve', ApproveRequirementDocumentController::class);
    Route::get('/requirements/{requirementId}', GetRequirementController::class);
    Route::post('/requirements/{requirementId}/approve', ApproveRequirementController::class);
});
