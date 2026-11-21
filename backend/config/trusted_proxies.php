<?php

// Lists the load balancer or reverse proxy addresses that are trusted to report the real client address.
// An empty list trusts no proxy, so the client address is always the direct peer address.

return [
    'addresses' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
    )),
];
