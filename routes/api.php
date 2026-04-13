<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\InstallationController;
use App\Http\Controllers\Internal\AuthorizeController;
use App\Http\Controllers\Internal\EntitlementController;
use App\Http\Controllers\Internal\UsageController;
use App\Http\Middleware\AuthenticateInstallation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Admin API (Sanctum auth — for the management panel)
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/installations', [InstallationController::class, 'index']);
    Route::post('/installations', [InstallationController::class, 'store']);
    Route::get('/installations/{installation}', [InstallationController::class, 'show']);
    Route::put('/installations/{installation}', [InstallationController::class, 'update']);
    Route::get('/installations/{installation}/entitlements', [InstallationController::class, 'entitlements']);
    Route::patch('/installations/{installation}/usage', [InstallationController::class, 'syncUsage']);
    Route::post('/installations/{installation}/regenerate-key', [InstallationController::class, 'regenerateApiKey']);
    Route::get('/installations/{installation}/audit-logs', [InstallationController::class, 'auditLogs']);
});

/*
|--------------------------------------------------------------------------
| Internal API (Management System)
|--------------------------------------------------------------------------
|
| These routes are private, backend-to-backend only.
| Authenticated via X-API-Key header per installation.
|
*/

Route::prefix('internal')
    ->middleware(AuthenticateInstallation::class)
    ->group(function () {
        Route::post('/authorize', AuthorizeController::class);
        Route::post('/usage', UsageController::class);
        Route::get('/entitlements', EntitlementController::class);
    });
