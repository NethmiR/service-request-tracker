<?php

// Builds every figure of the overview page from one reading of the current time.
// It only reads, so it opens no transaction and each figure comes from its own aggregate query.

namespace App\Services\Overview;

use App\Data\Overview\Overview;
use App\Data\Overview\OverviewHighlights;
use App\Models\User;
use Carbon\CarbonImmutable;

final class OverviewService
{
    public function __construct(
        private readonly ReportingCalendar $calendar,
        private readonly ServiceRequestStatistics $statistics,
        private readonly OverviewHighlightsRepository $highlights,
    ) {}

    public function build(User $viewer): Overview
    {
        $now = CarbonImmutable::now();
        $months = $this->calendar->months($now);

        return new Overview(
            $now,
            $this->calendar->timeZone(),
            $this->statistics->statusCounts(),
            new OverviewHighlights(
                $this->highlights->staleAfterDays(),
                $this->highlights->oldestOpen(),
                $this->highlights->assignedTo($viewer),
                $this->highlights->stale($now),
                $this->highlights->recentlyResolved(),
            ),
            $this->statistics->monthlyTrends($months),
            $this->statistics->tagCounts($months[array_key_first($months)], $months[array_key_last($months)]),
            $this->statistics->staffWorkload(),
        );
    }
}
