<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\LeadController;
use Illuminate\Support\Facades\Route;

/*
| All routes here are prefixed with /api, always respond with JSON, and
| authenticate with a Sanctum personal access token:
|     Authorization: Bearer <token>
*/

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.login');

Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->name('api.')->group(function () {
    Route::get('me', [AuthController::class, 'me'])->name('me');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::apiResource('leads', LeadController::class);
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');

    Route::apiResource('customers', CustomerController::class)->only(['index', 'show']);
});
