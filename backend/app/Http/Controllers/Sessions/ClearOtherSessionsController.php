<?php

// Handles ending every session of the signed in user except the current one.
// Delegates to the session management service and returns no content.

namespace App\Http\Controllers\Sessions;

use App\Data\Auth\RequestContext;
use App\Http\Controllers\Controller;
use App\Services\Sessions\SessionManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ClearOtherSessionsController extends Controller
{
    public function __invoke(Request $request, SessionManagementService $service): Response
    {
        $service->clearOtherOwnSessions($request->user(), $request->session()->getId(), RequestContext::fromRequest($request));

        return response()->noContent();
    }
}
