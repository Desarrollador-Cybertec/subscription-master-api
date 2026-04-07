<?php

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
