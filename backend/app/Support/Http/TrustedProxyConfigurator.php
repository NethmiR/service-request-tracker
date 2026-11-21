<?php

// Feeds the configured proxy addresses to TrustProxies, which already sits in Laravel's global middleware stack.
// Only the standard forwarded headers are trusted, and nothing is trusted when no address is configured.

namespace App\Support\Http;

use Illuminate\Http\Middleware\TrustProxies;
use Symfony\Component\HttpFoundation\Request;

final class TrustedProxyConfigurator
{
    private const int TRUSTED_HEADERS = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO;

    public static function configure(array $addresses): void
    {
        if ($addresses === []) {
            return;
        }

        TrustProxies::at($addresses);
        TrustProxies::withHeaders(self::TRUSTED_HEADERS);
    }
}
