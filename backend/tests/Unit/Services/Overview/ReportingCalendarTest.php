<?php

// Verifies the twelve reporting months built by the reporting calendar.
// Covers the order, the contiguous boundaries, the year roll over and a reporting time zone other than UTC.

namespace Tests\Unit\Services\Overview;

use App\Data\Overview\ReportingMonth;
use App\Services\Overview\ReportingCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReportingCalendarTest extends TestCase
{
    private const string UTC = 'UTC';

    private const string COLOMBO = 'Asia/Colombo';

    private const string CURRENT_TIME = '2026-11-21T08:30:00Z';

    private const array MONTH_KEYS = [
        '2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05',
        '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11',
    ];

    private const array MONTH_KEYS_FROM_JANUARY = [
        '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07',
        '2026-08', '2026-09', '2026-10', '2026-11', '2026-12', '2027-01',
    ];

    public function test_it_returns_twelve_months_oldest_first_ending_with_the_current_month(): void
    {
        $months = $this->calendar(self::UTC)->months(CarbonImmutable::parse(self::CURRENT_TIME));

        $this->assertCount(ReportingCalendar::MONTH_COUNT, $months);
        $this->assertSame(self::MONTH_KEYS, $this->keysOf($months));
        $this->assertTrue($months[0]->startsAt->equalTo(CarbonImmutable::parse('2025-12-01T00:00:00Z')));
    }

    public function test_each_month_ends_where_the_next_begins(): void
    {
        $months = $this->calendar(self::UTC)->months(CarbonImmutable::parse(self::CURRENT_TIME));

        foreach (array_slice($months, 0, -1) as $index => $month) {
            $this->assertTrue($month->endsAt->equalTo($months[$index + 1]->startsAt), $month->key);
        }

        $this->assertTrue($months[ReportingCalendar::MONTH_COUNT - 1]->endsAt->equalTo(CarbonImmutable::parse('2026-12-01T00:00:00Z')));
    }

    public function test_the_year_rolls_over_when_the_current_month_is_january(): void
    {
        $months = $this->calendar(self::UTC)->months(CarbonImmutable::parse('2027-01-15T10:00:00Z'));

        $this->assertSame(self::MONTH_KEYS_FROM_JANUARY, $this->keysOf($months));
        $this->assertTrue($months[10]->startsAt->equalTo(CarbonImmutable::parse('2026-12-01T00:00:00Z')));
        $this->assertTrue($months[11]->endsAt->equalTo(CarbonImmutable::parse('2027-02-01T00:00:00Z')));
    }

    public function test_months_follow_the_reporting_time_zone(): void
    {
        $calendar = $this->calendar(self::COLOMBO);
        $months = $calendar->months(CarbonImmutable::parse(self::CURRENT_TIME));
        $november = $months[ReportingCalendar::MONTH_COUNT - 1];

        $this->assertSame(self::COLOMBO, $calendar->timeZone());
        $this->assertSame('2026-11', $november->key);
        $this->assertTrue($november->startsAt->equalTo(CarbonImmutable::parse('2026-10-31T18:30:00Z')));
        $this->assertSame(self::UTC, $november->startsAt->getTimezone()->getName());
    }

    public function test_a_late_utc_time_already_belongs_to_the_next_month_in_the_reporting_time_zone(): void
    {
        $months = $this->calendar(self::COLOMBO)->months(CarbonImmutable::parse('2026-11-30T19:00:00Z'));

        $this->assertSame('2026-12', $months[ReportingCalendar::MONTH_COUNT - 1]->key);
        $this->assertSame('2026-01', $months[0]->key);
    }

    private function calendar(string $reportingTimeZone): ReportingCalendar
    {
        return new ReportingCalendar($reportingTimeZone, self::UTC);
    }

    private function keysOf(array $months): array
    {
        return array_map(fn (ReportingMonth $month): string => $month->key, $months);
    }
}
