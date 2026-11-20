<?php

// Names the rate limiters registered in the application service provider.
// Routes reference these cases so no limiter name is written as a string.

namespace App\Enums;

enum RateLimiterName: string
{
    case Api = 'api';
    case PasswordChange = 'password-change';
}
