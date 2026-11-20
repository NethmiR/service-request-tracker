<?php

// Shapes the signed in user for the frontend.
// Whitelists only safe identity fields and never exposes hashes or timestamps.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CurrentUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'password_change_required' => $this->requiresPasswordChange(),
        ];
    }
}
