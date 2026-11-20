<?php

// Builds the application instance with its routing, middleware and exception handling.
// This is the single place where global middleware, the middleware priority and exception rendering are registered.

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\DomainException;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureJsonResponse;
use App\Http\Middleware\EnsurePasswordChanged;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([AddSecurityHeaders::class, EnsureJsonResponse::class]);
        $middleware->statefulApi();
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            EnsurePasswordChanged::class,
            Authorize::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(
            fn (Throwable $exception) => app(ApiExceptionRenderer::class)->render($exception),
        );
        $exceptions->dontReport(DomainException::class);
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation']);
    })->create();
