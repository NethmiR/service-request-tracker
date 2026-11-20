<?php

// Handles an admin listing the active sessions of any user.
// Delegates to the session management service and returns the sessions through the session resource.

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use App\Models\User;
use App\Services\Sessions\SessionManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListUserSessionsController extends Controller
{
    public function __invoke(Request $request, User $user, SessionManagementService $service): AnonymousResourceCollection
    {
        return SessionResource::collection(
            $service->listUserSessions($user, $request->session()->getId()),
        );
    }
}
