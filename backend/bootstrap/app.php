<?php

// Builds the application instance with its routing, middleware and exception handling.
// This is the single place where global middleware and exception rendering are registered.

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\DomainException;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([AddSecurityHeaders::class, EnsureJsonResponse::class]);
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(
            fn (Throwable $exception) => app(ApiExceptionRenderer::class)->render($exception),
        );
        $exceptions->dontReport(DomainException::class);
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation']);
    })->create();
