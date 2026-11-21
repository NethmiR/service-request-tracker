<?php

// Carries the number of service requests currently in one status for an overview count box.
// A status with no requests is carried with a zero count.

namespace App\Data\Overview;

use App\Enums\ServiceRequestStatus;

final readonly class StatusCount
{
    public function __construct(
        public ServiceRequestStatus $status,
        public int $count,
    ) {}
}
