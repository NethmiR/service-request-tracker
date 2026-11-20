<?php

// Lists the statuses a service request moves through.
// The frontend mirrors these values in enums/service-request-status.ts.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum ServiceRequestStatus: string
{
    use ProvidesValues;

    case Open = 'OPEN';
    case Assigned = 'ASSIGNED';
    case InProgress = 'IN_PROGRESS';
    case Resolved = 'RESOLVED';
}
