<?php

// Carries the requests created and resolved in one reporting month and their average resolution time.
// The average is empty for a month in which no request was resolved.

namespace App\Data\Overview;

final readonly class MonthlyTrend
{
    public function __construct(
        public string $month,
        public int $created,
        public int $resolved,
        public ?float $averageResolutionSeconds,
    ) {}
}
