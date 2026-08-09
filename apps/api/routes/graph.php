<?php

declare(strict_types=1);

use App\Graph\Presentation\Controllers\ApproveArtifactVersionController;
use App\Graph\Presentation\Controllers\CreateArtifactController;
use App\Graph\Presentation\Controllers\CreateArtifactVersionController;
use App\Graph\Presentation\Controllers\CreateProjectController;
use App\Graph\Presentation\Controllers\GetArtifactController;
use App\Graph\Presentation\Controllers\GetProjectController;
use App\Graph\Presentation\Controllers\LinkArtifactVersionsController;
use App\Graph\Presentation\Controllers\ListApprovalsController;
use App\Graph\Presentation\Controllers\ListArtifactVersionLinksController;
use App\Graph\Presentation\Controllers\ListProjectArtifactsController;
use App\Graph\Presentation\Controllers\ListProjectsController;
use App\Graph\Presentation\Controllers\TraverseImpactController;
use App\Tenancy\Presentation\BindTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', BindTenantContext::class])->group(function (): void {
    Route::post('/projects', CreateProjectController::class);
    Route::get('/projects', ListProjectsController::class);
    Route::get('/projects/{projectId}', GetProjectController::class);
    Route::get('/projects/{projectId}/artifacts', ListProjectArtifactsController::class);
    Route::post('/artifacts', CreateArtifactController::class);
    Route::get('/artifacts/{artifactId}', GetArtifactController::class);
    Route::post('/artifacts/{artifactId}/versions', CreateArtifactVersionController::class);
    Route::post('/artifact-links', LinkArtifactVersionsController::class);
    Route::get('/artifact-versions/{versionId}/links', ListArtifactVersionLinksController::class);
    Route::post('/artifact-versions/{versionId}/approvals', ApproveArtifactVersionController::class);
    Route::get('/artifact-versions/{versionId}/approvals', ListApprovalsController::class);
    Route::get('/artifact-versions/{versionId}/impact', TraverseImpactController::class);
});
