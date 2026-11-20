<?php

// Carries the validated email and password of a sign in attempt.
// Keeps services independent of the HTTP request.

namespace App\Data\Auth;

final readonly class SignInCredentials
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
