<?php

// Settings for workflow changes on service requests.
// The lock timeout decides how long a change waits for the request row lock before it is rejected as a conflict.

return [
    'lock_timeout_milliseconds' => (int) env('WORKFLOW_LOCK_TIMEOUT_MILLISECONDS', 3000),
];
