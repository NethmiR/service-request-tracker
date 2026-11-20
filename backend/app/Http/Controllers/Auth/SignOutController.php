<?php

// Handles the sign out endpoint.
// Delegates to the authentication service and returns no content.

namespace App\Http\Controllers\Auth;

use App\Data\Auth\RequestContext;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SignOutController extends Controller
{
    public function __invoke(Request $request, AuthenticationService $service): Response
    {
        $service->signOut($request->user(), $request->session(), RequestContext::fromRequest($request));

        return response()->noContent();
    }
}
