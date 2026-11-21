<?php

// Carries every figure of the overview page, all computed from the same moment.
// The time zone names the reporting time zone the monthly figures follow.

namespace App\Data\Overview;

use Carbon\CarbonImmutable;

final readonly class Overview
{
    public function __construct(
        public CarbonImmutable $generatedAt,
        public string $timeZone,
        public array $statusCounts,
        public OverviewHighlights $highlights,
        public array $monthlyTrends,
        public array $tagCounts,
        public array $staffWorkload,
    ) {}
}
