<?php

// Handles the current user endpoint.
// Returns only the safe identity fields of the signed in user.

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\Request;

final class CurrentUserController extends Controller
{
    public function __invoke(Request $request): CurrentUserResource
    {
        return new CurrentUserResource($request->user());
    }
}
