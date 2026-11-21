<?php

// Lists the predefined priorities a service request can carry, from lowest to highest.
// The frontend mirrors these values in enums/service-request-priority.ts.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum ServiceRequestPriority: string
{
    use ProvidesValues;

    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
    case Critical = 'CRITICAL';
}
