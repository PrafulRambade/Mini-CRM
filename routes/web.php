<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('leads', LeadController::class);
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Administration (authorization enforced by UserPolicy).
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
});
