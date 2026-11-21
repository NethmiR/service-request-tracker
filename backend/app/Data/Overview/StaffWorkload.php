<?php

// Carries the requests currently assigned to one technician or admin, split into assigned and in progress.
// Resolved requests are not workload and are never counted here.

namespace App\Data\Overview;

use App\Models\User;

final readonly class StaffWorkload
{
    public function __construct(
        public User $user,
        public int $assigned,
        public int $inProgress,
    ) {}
}
