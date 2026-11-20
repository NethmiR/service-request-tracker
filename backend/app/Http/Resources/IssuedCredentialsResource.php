<?php

// Shapes a created or reset user together with the temporary password to display once.
// The temporary password is null when the delivery sent it elsewhere.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class IssuedCredentialsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->user),
            'temporary_password' => $this->temporaryPassword,
        ];
    }
}
