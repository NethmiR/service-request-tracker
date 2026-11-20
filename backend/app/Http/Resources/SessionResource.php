<?php

// Shapes one active session for the frontend.
// Exposes only the non reversible identifier and never the raw session id, the user id or the payload.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->identifier,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'last_active_at' => $this->lastActiveAt->toIso8601ZuluString(),
            'is_current' => $this->isCurrent,
        ];
    }
}
