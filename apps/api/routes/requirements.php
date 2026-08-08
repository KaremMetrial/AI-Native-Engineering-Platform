<?php

declare(strict_types=1);

use App\Requirements\Presentation\Controllers\AddRequirementController;
use App\Requirements\Presentation\Controllers\ApproveRequirementController;
use App\Requirements\Presentation\Controllers\ApproveRequirementDocumentController;
use App\Requirements\Presentation\Controllers\CreateRequirementDocumentController;
use App\Tenancy\Presentation\BindTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', BindTenantContext::class])->group(function (): void {
    Route::post('/requirement-documents', CreateRequirementDocumentController::class);
    Route::post('/requirement-documents/{documentId}/requirements', AddRequirementController::class);
    Route::post('/requirement-documents/{documentId}/approve', ApproveRequirementDocumentController::class);
    Route::post('/requirements/{requirementId}/approve', ApproveRequirementController::class);
});
