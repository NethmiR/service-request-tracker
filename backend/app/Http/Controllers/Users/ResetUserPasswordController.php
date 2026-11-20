<?php

// Handles an admin resetting a user's password to a generated temporary password.
// Delegates to the user management service and returns the user with the password to display once.

namespace App\Http\Controllers\Users;

use App\Data\Auth\RequestContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\IssuedCredentialsResource;
use App\Models\User;
use App\Services\Users\UserManagementService;
use Illuminate\Http\Request;

final class ResetUserPasswordController extends Controller
{
    public function __invoke(Request $request, User $user, UserManagementService $service): IssuedCredentialsResource
    {
        return new IssuedCredentialsResource(
            $service->resetPassword($request->user(), $user, RequestContext::fromRequest($request)),
        );
    }
}
