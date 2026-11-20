<?php

// Handles ending one session of the signed in user other than the current one.
// Finds the session, authorizes it through the session policy and returns no content.

namespace App\Http\Controllers\Sessions;

use App\Data\Auth\RequestContext;
use App\Enums\PolicyAbility;
use App\Http\Controllers\Controller;
use App\Services\Sessions\SessionManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class ClearSessionController extends Controller
{
    public function __invoke(Request $request, string $sessionIdentifier, SessionManagementService $service): Response
    {
        $session = $service->findOwnSession($request->user(), $sessionIdentifier, $request->session()->getId());

        Gate::authorize(PolicyAbility::Delete->value, $session);

        $service->clearOwnSession($request->user(), $session, RequestContext::fromRequest($request));

        return response()->noContent();
    }
}
