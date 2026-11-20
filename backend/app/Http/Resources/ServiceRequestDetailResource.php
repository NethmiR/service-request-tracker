<?php

// Shapes a service request for the detail page and the create response.
// Adds the description and both histories to the summary fields, which are defined once in the summary resource.

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class ServiceRequestDetailResource extends ServiceRequestSummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'status_history' => ServiceRequestStatusHistoryResource::collection($this->statusHistories),
            'assignee_history' => ServiceRequestAssigneeHistoryResource::collection($this->assigneeHistories),
        ];
    }
}
