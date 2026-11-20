<?php

// Decides which users may view a service request.
// The requester and anyone who passes the view all service requests Gate may view it, and everyone else is told it does not exist.

namespace App\Policies;

use App\Enums\GateAbility;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class ServiceRequestPolicy
{
    public function view(User $user, ServiceRequest $serviceRequest): Response
    {
        if ($serviceRequest->isRequestedBy($user) || $user->can(GateAbility::ViewAllServiceRequests->value)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }
}
