<?php

// Works out which workflow actions a user may take on a service request in its current state.
// Combines the same Gate, policy and status rules the workflow endpoints enforce, so the frontend never repeats them.

namespace App\Data\ServiceRequests;

use App\Enums\GateAbility;
use App\Enums\PolicyAbility;
use App\Models\ServiceRequest;
use App\Models\User;

final readonly class AvailableActions
{
    public function __construct(
        public bool $canAssignToSelf,
        public bool $canReassign,
        public array $allowedStatuses,
    ) {}

    public static function for(ServiceRequest $serviceRequest, User $viewer): self
    {
        return new self(
            $viewer->can(GateAbility::HandleServiceRequests->value) && $serviceRequest->status->isAssignable(),
            $viewer->can(PolicyAbility::Reassign->value, $serviceRequest),
            $viewer->can(PolicyAbility::ChangeStatus->value, $serviceRequest)
                ? $serviceRequest->status->allowedTargets()
                : [],
        );
    }
}
