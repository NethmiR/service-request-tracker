<?php

// Handles listing the active sessions of the signed in user.
// Delegates to the session management service and returns the sessions through the session resource.

namespace App\Http\Controllers\Sessions;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use App\Services\Sessions\SessionManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListSessionsController extends Controller
{
    public function __invoke(Request $request, SessionManagementService $service): AnonymousResourceCollection
    {
        return SessionResource::collection(
            $service->listOwnSessions($request->user(), $request->session()->getId()),
        );
    }
}
