<?php

// Shapes the current workload of one technician or admin for the overview workload graph.
// The user is returned as a short reference with only the id and the name.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StaffWorkloadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserReferenceResource($this->user),
            'assigned' => $this->assigned,
            'in_progress' => $this->inProgress,
        ];
    }
}
