<?php

// Settings for the overview page of technicians and admins.
// The time zone decides where reporting months begin and end, and the stale threshold decides when an unresolved request is highlighted.

return [
    'time_zone' => env('OVERVIEW_TIME_ZONE', 'UTC'),

    'stale_after_days' => (int) env('OVERVIEW_STALE_AFTER_DAYS', 7),
];
