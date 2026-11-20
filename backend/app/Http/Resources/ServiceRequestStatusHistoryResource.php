<?php

// Shapes one status history record of a service request.
// The initial record has an empty from status and an empty actor.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceRequestStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status->value,
            'changed_by' => $this->changedBy === null ? null : new UserReferenceResource($this->changedBy),
            'remark' => $this->remark,
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
