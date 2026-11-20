<?php

// Lists the predefined tags a service request can carry.
// The frontend mirrors these values in enums/service-request-tag.ts.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum ServiceRequestTag: string
{
    use ProvidesValues;

    case Hardware = 'HARDWARE';
    case Software = 'SOFTWARE';
    case Network = 'NETWORK';
    case Other = 'OTHER';
}
