<?php

// Credentials for the initial admin created by the initial admin seeder.
// Values come only from environment variables and are never committed.

return [
    'name' => env('INITIAL_ADMIN_NAME'),
    'email' => env('INITIAL_ADMIN_EMAIL'),
    'password' => env('INITIAL_ADMIN_PASSWORD'),
];
