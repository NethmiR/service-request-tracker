<?php

// Marks a response as never to be stored by the browser or by any proxy.
// Applied to responses that carry a temporary password, whatever the request method.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PreventResponseCaching
{
    private const array CACHE_OPTIONS = [
        'no_store' => true,
        'private' => true,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->setCache(self::CACHE_OPTIONS);

        return $response;
    }
}
