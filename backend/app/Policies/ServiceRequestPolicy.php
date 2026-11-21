<?php

// Decides which users may view a service request and who may work on it.
// Viewing is open to the requester and to anyone who passes the view all Gate, and others are told it does not exist.
// Reassigning and changing the status are open only to the current assignee, and everyone else is forbidden.

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

    public function reassign(User $user, ServiceRequest $serviceRequest): Response
    {
        return $this->allowCurrentAssignee($user, $serviceRequest);
    }

    public function changeStatus(User $user, ServiceRequest $serviceRequest): Response
    {
        return $this->allowCurrentAssignee($user, $serviceRequest);
    }

    private function allowCurrentAssignee(User $user, ServiceRequest $serviceRequest): Response
    {
        return $serviceRequest->isAssignedTo($user) ? Response::allow() : Response::deny();
    }
}
