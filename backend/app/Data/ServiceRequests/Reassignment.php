<?php

// Carries the validated new assignee and remark of a service request being reassigned.
// Keeps the workflow service independent of the HTTP request.

namespace App\Data\ServiceRequests;

final readonly class Reassignment
{
    public function __construct(
        public int $assigneeId,
        public string $remark,
    ) {}
}
