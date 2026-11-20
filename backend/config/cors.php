<?php

// Cross origin resource sharing rules for the API.
// Only the configured frontend origin may send credentialed requests with the methods and headers the API uses.

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE'],

    'allowed_origins' => [env('FRONTEND_URL')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 7200,

    'supports_credentials' => true,
];
