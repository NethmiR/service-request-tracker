<?php

// Defines the REST API routes, all served under the api prefix.
// Each phase adds its routes here together with the matching frontend endpoints.

use App\Enums\GateAbility;
use App\Enums\RateLimiterName;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\ServiceRequests\CreateServiceRequestController;
use App\Http\Controllers\ServiceRequests\ListOwnServiceRequestsController;
use App\Http\Controllers\ServiceRequests\ShowServiceRequestController;
use App\Http\Controllers\Sessions\ClearOtherSessionsController;
use App\Http\Controllers\Sessions\ClearSessionController;
use App\Http\Controllers\Sessions\ListSessionsController;
use App\Http\Controllers\Users\ClearUserSessionController;
use App\Http\Controllers\Users\ClearUserSessionsController;
use App\Http\Controllers\Users\CreateUserController;
use App\Http\Controllers\Users\ListUsersController;
use App\Http\Controllers\Users\ListUserSessionsController;
use App\Http\Controllers\Users\ResetUserPasswordController;
use App\Http\Controllers\Users\ShowUserController;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureSessionStarted;
use App\Http\Middleware\PreventResponseCaching;
use App\Services\Sessions\SessionIdentifier;
use Illuminate\Auth\Middleware\Authorize;
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

        Route::post('service-requests', CreateServiceRequestController::class)->name('service-requests.store');
        Route::get('service-requests/mine', ListOwnServiceRequestsController::class)->name('service-requests.mine');
        Route::get('service-requests/{serviceRequest}', ShowServiceRequestController::class)
            ->whereNumber('serviceRequest')
            ->name('service-requests.show');

        Route::middleware(Authorize::using(GateAbility::ManageUsers->value))->group(function (): void {
            Route::get('users', ListUsersController::class)->name('users.index');
            Route::post('users', CreateUserController::class)
                ->middleware([
                    ThrottleRequests::using(RateLimiterName::UserCreation->value),
                    PreventResponseCaching::class,
                ])
                ->name('users.store');
            Route::get('users/{user}', ShowUserController::class)->whereNumber('user')->name('users.show');
            Route::post('users/{user}/password-reset', ResetUserPasswordController::class)
                ->whereNumber('user')
                ->middleware([
                    ThrottleRequests::using(RateLimiterName::AdminPasswordReset->value),
                    PreventResponseCaching::class,
                ])
                ->name('users.password-reset');
            Route::get('users/{user}/sessions', ListUserSessionsController::class)
                ->whereNumber('user')
                ->name('users.sessions.index');
            Route::delete('users/{user}/sessions', ClearUserSessionsController::class)
                ->whereNumber('user')
                ->name('users.sessions.destroy-all');
            Route::delete('users/{user}/sessions/{sessionIdentifier}', ClearUserSessionController::class)
                ->whereNumber('user')
                ->where('sessionIdentifier', SessionIdentifier::PATTERN)
                ->name('users.sessions.destroy');
        });
    });
});
