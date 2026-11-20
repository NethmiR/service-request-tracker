<?php

// Adds the security headers to every response and hides the PHP version.
// Strict transport security is sent only when the application runs in production.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddSecurityHeaders
{
    private const array COMMON_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    private const string STRICT_TRANSPORT_SECURITY_HEADER = 'Strict-Transport-Security';

    private const string STRICT_TRANSPORT_SECURITY_VALUE = 'max-age=31536000; includeSubDomains';

    private const string POWERED_BY_HEADER = 'X-Powered-By';

    public function __construct(private readonly Application $application) {}

    public function handle(Request $request, Closure $next): Response
    {
        header_remove(self::POWERED_BY_HEADER);

        $response = $next($request);

        $response->headers->add(self::COMMON_HEADERS);

        if ($this->application->isProduction()) {
            $response->headers->set(self::STRICT_TRANSPORT_SECURITY_HEADER, self::STRICT_TRANSPORT_SECURITY_VALUE);
        }

        return $response;
    }
}
