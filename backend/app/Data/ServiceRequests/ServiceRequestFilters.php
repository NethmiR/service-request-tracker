<?php

// Carries the validated status, assignee, priority and title search filters of a request list.
// Keeps the service request service independent of the HTTP request.

namespace App\Data\ServiceRequests;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;

final readonly class ServiceRequestFilters
{
    public function __construct(
        public ?ServiceRequestStatus $status = null,
        public ?int $assigneeId = null,
        public ?ServiceRequestPriority $priority = null,
        public ?string $search = null,
    ) {}
}
