<?php

// Carries the validated remark of a user assigning an open service request to themselves.
// Keeps the workflow service independent of the HTTP request.

namespace App\Data\ServiceRequests;

final readonly class SelfAssignment
{
    public function __construct(
        public string $remark,
    ) {}
}
