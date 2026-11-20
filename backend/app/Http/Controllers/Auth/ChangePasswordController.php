<?php

// Handles the self service password change endpoint.
// Delegates to the password change service and returns the updated current user.

namespace App\Http\Controllers\Auth;

use App\Data\Auth\RequestContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\CurrentUserResource;
use App\Services\Auth\PasswordChangeService;

final class ChangePasswordController extends Controller
{
    public function __invoke(ChangePasswordRequest $request, PasswordChangeService $service): CurrentUserResource
    {
        $user = $service->change(
            $request->user(),
            $request->toPasswordChange(),
            $request->session(),
            RequestContext::fromRequest($request),
        );

        return new CurrentUserResource($user);
    }
}
