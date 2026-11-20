<?php

// Defines the REST API routes, all served under the api prefix.
// Each phase adds its routes here together with the matching frontend endpoints.

use App\Enums\RateLimiterName;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\Sessions\ClearOtherSessionsController;
use App\Http\Controllers\Sessions\ClearSessionController;
use App\Http\Controllers\Sessions\ListSessionsController;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureSessionStarted;
use App\Services\Sessions\SessionIdentifier;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::post('auth/sign-in', SignInController::class)
    ->middleware(EnsureSessionStarted::class)
    ->name('auth.sign-in');

Route::middleware(['auth:sanctum', ThrottleRequests::using(RateLimiterName::Api->value)])->group(function (): void {
    Route::get('auth/user', CurrentUserController::class)->name('auth.user');
    Route::post('auth/sign-out', SignOutController::class)->name('auth.sign-out');
    Route::patch('auth/password', ChangePasswordController::class)
        ->middleware(ThrottleRequests::using(RateLimiterName::PasswordChange->value))
        ->name('auth.password');

    Route::middleware(EnsurePasswordChanged::class)->group(function (): void {
        Route::get('sessions', ListSessionsController::class)->name('sessions.index');
        Route::delete('sessions/others', ClearOtherSessionsController::class)->name('sessions.destroy-others');
        Route::delete('sessions/{sessionIdentifier}', ClearSessionController::class)
            ->where('sessionIdentifier', SessionIdentifier::PATTERN)
            ->name('sessions.destroy');
    });
});
