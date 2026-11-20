<?php

// Sanctum settings for SPA cookie authentication from the frontend origin.
// Personal access tokens are not used, so expiry is controlled by the session lifetime.

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [
    'stateful' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''))),
    )),

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => '',

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
