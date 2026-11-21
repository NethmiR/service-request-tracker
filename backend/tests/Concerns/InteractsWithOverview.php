<?php

// Prepares overview feature tests with a fixed current time and known overview settings.
// The fixed time keeps month boundaries deterministic and the settings never depend on the local environment file.

namespace Tests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

trait InteractsWithOverview
{
    protected const string OVERVIEW_ROUTE = '/api/overview';

    protected const string OVERVIEW_NOW = '2026-11-21T08:30:00Z';

    protected const string DEFAULT_REPORTING_TIME_ZONE = 'UTC';

    protected const int DEFAULT_STALE_AFTER_DAYS = 7;

    protected function setUpInteractsWithOverview(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::OVERVIEW_NOW));

        config([
            'overview.time_zone' => self::DEFAULT_REPORTING_TIME_ZONE,
            'overview.stale_after_days' => self::DEFAULT_STALE_AFTER_DAYS,
        ]);
    }

    protected function requestOverview(): TestResponse
    {
        return $this->getJson(self::OVERVIEW_ROUTE);
    }

    protected function fetchOverview(): array
    {
        return $this->requestOverview()->assertOk()->json('data');
    }

    protected function daysAgo(int $days): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($days);
    }

    protected function idsOf(array $items): array
    {
        return array_column($items, 'id');
    }
}
