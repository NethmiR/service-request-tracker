<?php

// Handles the sign in endpoint.
// Delegates to the authentication service and returns the current user.

namespace App\Http\Controllers\Auth;

use App\Data\Auth\RequestContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Resources\CurrentUserResource;
use App\Services\Auth\AuthenticationService;

final class SignInController extends Controller
{
    public function __invoke(SignInRequest $request, AuthenticationService $service): CurrentUserResource
    {
        $user = $service->signIn($request->toCredentials(), RequestContext::fromRequest($request));

        return new CurrentUserResource($user);
    }
}
