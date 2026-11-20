<?php

// Shapes one assignee history record of a service request.
// The first assignment has an empty assigned from user.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceRequestAssigneeHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assigned_from' => $this->assignedFrom === null ? null : new UserReferenceResource($this->assignedFrom),
            'assigned_to' => new UserReferenceResource($this->assignedTo),
            'remark' => $this->remark,
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
