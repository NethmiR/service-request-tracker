<?php

// Limits for sign in attempts, password changes and general API usage.
// Every value can be overridden through environment variables.

return [
    'sign_in' => [
        'max_failures_per_email_and_ip' => (int) env('SIGN_IN_MAX_FAILURES_PER_EMAIL_AND_IP', 5),
        'max_failures_per_ip' => (int) env('SIGN_IN_MAX_FAILURES_PER_IP', 20),
        'decay_seconds' => (int) env('SIGN_IN_DECAY_SECONDS', 60),
    ],
    'password_change' => [
        'max_attempts_per_minute' => (int) env('PASSWORD_CHANGE_MAX_ATTEMPTS_PER_MINUTE', 5),
    ],
    'api' => [
        'max_requests_per_minute' => (int) env('API_MAX_REQUESTS_PER_MINUTE', 120),
    ],
];
