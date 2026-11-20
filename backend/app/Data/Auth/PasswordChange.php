<?php

// Carries the validated new password of a self service password change.
// Keeps services independent of the HTTP request.

namespace App\Data\Auth;

final readonly class PasswordChange
{
    public function __construct(
        public string $newPassword,
    ) {}
}
