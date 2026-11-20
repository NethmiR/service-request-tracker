<?php

// Carries the validated name, email and role of a user created by an admin.
// Keeps the user management service independent of the HTTP request.

namespace App\Data\Users;

use App\Enums\UserRole;

final readonly class NewUser
{
    public function __construct(
        public string $name,
        public string $email,
        public UserRole $role,
    ) {}
}
