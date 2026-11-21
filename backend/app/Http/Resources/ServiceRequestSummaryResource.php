<?php

// Shapes a service request for the request lists, resolving the current assignee from the latest assignee history record.
// Whitelists the list fields, including the priority, and adds the workflow actions the viewing user may take on the request.

namespace App\Http\Resources;

use App\Data\ServiceRequests\AvailableActions;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assignee = $this->currentAssignee();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'priority' => $this->priorityTag->name->value,
            'tags' => $this->tags->map(fn (Tag $tag): string => $tag->name->value)->all(),
            'requested_by' => new UserReferenceResource($this->requester),
            'assignee' => $assignee === null ? null : new UserReferenceResource($assignee),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'available_actions' => new AvailableActionsResource(AvailableActions::for($this->resource, $request->user())),
        ];
    }
}
