<?php

// Shapes a user for the admin user management screens.
// Whitelists the profile fields and never exposes the password hash, the remember token or internal timestamps.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'password_change_required' => $this->requiresPasswordChange(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
