<?php

// Marks every incoming request as expecting JSON.
// Keeps validation, authentication and error handling in API mode for all clients.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureJsonResponse
{
    private const string ACCEPT_HEADER = 'Accept';

    private const string JSON_MEDIA_TYPE = 'application/json';

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set(self::ACCEPT_HEADER, self::JSON_MEDIA_TYPE);

        return $next($request);
    }
}
