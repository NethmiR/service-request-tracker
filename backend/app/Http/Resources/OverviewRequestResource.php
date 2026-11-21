<?php

// Shapes a service request shown in an overview highlight list, resolving the current assignee from the latest assignee history record.
// Whitelists the list fields and adds the time of the latest status record, leaving out tags, the description and workflow actions.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OverviewRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assignee = $this->currentAssignee();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'requested_by' => new UserReferenceResource($this->requester),
            'assignee' => $assignee === null ? null : new UserReferenceResource($assignee),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'last_status_changed_at' => $this->last_status_changed_at->toIso8601ZuluString(),
        ];
    }
}
