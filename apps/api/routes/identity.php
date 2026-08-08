<?php

declare(strict_types=1);

use App\Identity\Presentation\Controllers\AssignRoleController;
use App\Identity\Presentation\Controllers\LoginController;
use App\Identity\Presentation\Controllers\LogoutController;
use App\Identity\Presentation\Controllers\MeController;
use App\Identity\Presentation\Controllers\RegisterController;
use App\Tenancy\Presentation\BindTenantContext;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);

Route::middleware(['auth:sanctum', BindTenantContext::class])->group(function (): void {
    Route::post('/logout', LogoutController::class);
    Route::get('/me', MeController::class);
    Route::patch('/members/{userId}/role', AssignRoleController::class);
});
