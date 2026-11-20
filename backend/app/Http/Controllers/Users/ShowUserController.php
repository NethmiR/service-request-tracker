<?php

// Handles showing one user on the admin user detail page.
// The user is resolved by route model binding and returned through the user resource.

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;

final class ShowUserController extends Controller
{
    public function __invoke(User $user): UserResource
    {
        return new UserResource($user);
    }
}
