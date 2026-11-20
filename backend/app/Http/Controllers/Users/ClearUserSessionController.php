<?php

// Handles an admin ending one session of any user.
// Finds the session, authorizes it through the session policy and returns no content.

namespace App\Http\Controllers\Users;

use App\Data\Auth\RequestContext;
use App\Enums\PolicyAbility;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sessions\SessionManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class ClearUserSessionController extends Controller
{
    public function __invoke(Request $request, User $user, string $sessionIdentifier, SessionManagementService $service): Response
    {
        $session = $service->findUserSession($user, $sessionIdentifier, $request->session()->getId());

        Gate::authorize(PolicyAbility::Delete->value, $session);

        $service->clearUserSession($request->user(), $user, $session, RequestContext::fromRequest($request));

        return response()->noContent();
    }
}
