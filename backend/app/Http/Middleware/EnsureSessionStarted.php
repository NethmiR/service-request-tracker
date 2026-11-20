<?php

// Rejects requests that arrive without a session.
// Protects sign in from clients outside the stateful frontend origins, which would otherwise cause a server error.

namespace App\Http\Middleware;

use App\Exceptions\SessionRequiredException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSessionStarted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            throw new SessionRequiredException;
        }

        return $next($request);
    }
}
