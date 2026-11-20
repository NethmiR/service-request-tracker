<?php

// Blocks protected endpoints while the signed in user still has to change their password.
// It runs after authentication, so the server enforces the rule and not the frontend.

namespace App\Http\Middleware;

use App\Exceptions\PasswordChangeRequiredException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->requiresPasswordChange()) {
            throw new PasswordChangeRequiredException;
        }

        return $next($request);
    }
}
