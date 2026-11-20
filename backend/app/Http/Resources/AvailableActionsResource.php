<?php

// Shapes the workflow actions the viewing user may take on a service request.
// The frontend shows workflow controls only from these fields and never repeats the rules.

namespace App\Http\Resources;

use App\Enums\ServiceRequestStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AvailableActionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'can_assign_to_self' => $this->canAssignToSelf,
            'can_reassign' => $this->canReassign,
            'allowed_statuses' => array_map(
                fn (ServiceRequestStatus $status): string => $status->value,
                $this->allowedStatuses,
            ),
        ];
    }
}
