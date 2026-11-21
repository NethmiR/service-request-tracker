<?php

// Builds the twelve reporting months of the overview graphs, ending with the current month.
// Month boundaries follow the configured reporting time zone and are converted to the application time zone for binding.

namespace App\Services\Overview;

use App\Data\Overview\ReportingMonth;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;

final class ReportingCalendar
{
    public const int MONTH_COUNT = 12;

    private const string KEY_FORMAT = 'Y-m';

    public function __construct(
        #[Config('overview.time_zone')]
        private readonly string $reportingTimeZone,
        #[Config('app.timezone')]
        private readonly string $applicationTimeZone,
    ) {}

    public function timeZone(): string
    {
        return $this->reportingTimeZone;
    }

    public function months(CarbonImmutable $now): array
    {
        $currentMonthStart = $now->setTimezone($this->reportingTimeZone)->startOfMonth();
        $months = [];

        for ($offset = self::MONTH_COUNT - 1; $offset >= 0; $offset--) {
            $startsAt = $currentMonthStart->subMonthsNoOverflow($offset);
            $endsAt = $startsAt->addMonthNoOverflow();

            $months[] = new ReportingMonth(
                $startsAt->format(self::KEY_FORMAT),
                $startsAt->setTimezone($this->applicationTimeZone),
                $endsAt->setTimezone($this->applicationTimeZone),
            );
        }

        return $months;
    }
}
