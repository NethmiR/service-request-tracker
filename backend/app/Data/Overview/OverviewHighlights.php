<?php

// Carries the four highlight lists of the overview together with the stale threshold they were built with.
// Each list holds service request models with the requester and the current assignee eager loaded.

namespace App\Data\Overview;

use Illuminate\Database\Eloquent\Collection;

final readonly class OverviewHighlights
{
    public function __construct(
        public int $staleAfterDays,
        public Collection $oldestOpen,
        public Collection $assignedToViewer,
        public Collection $stale,
        public Collection $recentlyResolved,
    ) {}
}
