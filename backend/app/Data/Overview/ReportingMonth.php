<?php

// Describes one reporting month of the overview graphs by its key in the reporting time zone.
// Both bounds are in the application time zone, ready to be bound, and the end is exclusive.

namespace App\Data\Overview;

use Carbon\CarbonImmutable;

final readonly class ReportingMonth
{
    public function __construct(
        public string $key,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}
}
