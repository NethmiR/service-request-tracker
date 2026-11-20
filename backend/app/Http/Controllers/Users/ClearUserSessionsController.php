<?php

// Handles an admin ending every session of any user except the admin's own current session.
// Delegates to the session management service and returns no content.

namespace App\Http\Controllers\Users;

use App\Data\Auth\RequestContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sessions\SessionManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ClearUserSessionsController extends Controller
{
    public function __invoke(Request $request, User $user, SessionManagementService $service): Response
    {
        $service->clearUserSessions($request->user(), $user, $request->session()->getId(), RequestContext::fromRequest($request));

        return response()->noContent();
    }
}
