<?php

// Carries the validated target status and optional remark of a service request status change.
// Keeps the workflow service independent of the HTTP request.

namespace App\Data\ServiceRequests;

use App\Enums\ServiceRequestStatus;

final readonly class StatusChange
{
    public function __construct(
        public ServiceRequestStatus $status,
        public ?string $remark,
    ) {}
}
