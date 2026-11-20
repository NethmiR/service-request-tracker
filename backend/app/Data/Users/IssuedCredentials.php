<?php

// Carries a user together with the temporary password value returned by the delivery.
// The password is null when the delivery sends it elsewhere instead of displaying it once.

namespace App\Data\Users;

use App\Models\User;

final readonly class IssuedCredentials
{
    public function __construct(
        public User $user,
        public ?string $temporaryPassword,
    ) {}
}
