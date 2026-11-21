<?php

namespace App\Data\ServiceRequests;

use App\Enums\ServiceRequestStatus;

final readonly class ServiceRequestFilters
{
    public function __construct(
        public ?ServiceRequestStatus $status = null,
        public ?int $assigneeId = null,
    ) {}
}
