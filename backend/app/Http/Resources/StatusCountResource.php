<?php

// Shapes the number of service requests in one status for an overview count box.
// The status is returned as a value item, so no status name is ever written as a JSON key.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StatusCountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'count' => $this->count,
        ];
    }
}
