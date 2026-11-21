<?php

// Verifies the overview graph datasets for created and resolved requests per month, the average resolution time and the tag distribution.
// Covers the window edges, reopened requests and month boundaries in a reporting time zone other than UTC.

namespace Tests\Feature\Overview;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowHistory;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithOverview;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class OverviewTrendsTest extends TestCase
{
    use BuildsWorkflowHistory;
    use InteractsWithFrontendSession;
    use InteractsWithOverview;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string COLOMBO = 'Asia/Colombo';

    private const array MONTH_KEYS = [
        '2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05',
        '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11',
    ];

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAs(User::factory()->technician()->create());
        $this->technician = User::factory()->technician()->create();
    }

    public function test_monthly_trends_hold_twelve_months_ending_with_the_current_month(): void
    {
        $this->assertSame(self::MONTH_KEYS, array_column($this->fetchOverview()['monthly_trends'], 'month'));
    }

    public function test_created_counts_requests_in_their_creation_month(): void
    {
        $this->createServiceRequestAt($this->at('2025-11-30T23:59:59Z'));
        $this->createServiceRequestAt($this->at('2025-12-01T00:00:00Z'));
        $this->createServiceRequestAt($this->at('2026-06-15T12:00:00Z'));
        $this->createServiceRequestAt($this->at('2026-06-30T23:59:59Z'));
        $this->createServiceRequestAt($this->at('2026-11-20T10:00:00Z'));

        $trends = $this->trendsByMonth();

        $this->assertSame(1, $trends['2025-12']['created']);
        $this->assertSame(2, $trends['2026-06']['created']);
        $this->assertSame(1, $trends['2026-11']['created']);
        $this->assertSame(4, array_sum(array_column($trends, 'created')));
    }

    public function test_a_resolved_request_counts_in_the_month_of_its_resolution(): void
    {
        $serviceRequest = $this->createServiceRequestAt($this->at('2026-02-25T09:00:00Z'));
        $this->assignAt($serviceRequest, $this->technician, $this->at('2026-02-26T09:00:00Z'));
        $this->resolveAt($serviceRequest, $this->technician, $this->at('2026-03-03T09:00:00Z'));

        $trends = $this->trendsByMonth();

        $this->assertSame(0, $trends['2026-02']['resolved']);
        $this->assertSame(1, $trends['2026-03']['resolved']);
        $this->assertSame(1, array_sum(array_column($trends, 'resolved')));
    }

    public function test_a_request_resolved_again_counts_only_in_the_month_of_its_latest_resolution(): void
    {
        $serviceRequest = $this->createServiceRequestAt($this->at('2026-04-01T00:00:00Z'));
        $this->assignAt($serviceRequest, $this->technician, $this->at('2026-04-01T01:00:00Z'));
        $this->resolveAt($serviceRequest, $this->technician, $this->at('2026-04-02T00:00:00Z'));
        $this->changeStatusAt($serviceRequest, $this->technician, ServiceRequestStatus::Assigned, $this->at('2026-05-01T00:00:00Z'));
        $this->resolveAt($serviceRequest, $this->technician, $this->at('2026-06-01T00:00:00Z'));

        $trends = $this->trendsByMonth();

        $this->assertSame(0, $trends['2026-04']['resolved']);
        $this->assertNull($trends['2026-04']['average_resolution_hours']);
        $this->assertSame(1, $trends['2026-06']['resolved']);
        $this->assertEquals(1464, $trends['2026-06']['average_resolution_hours']);
    }

    public function test_a_reopened_request_that_is_still_open_is_not_counted_as_resolved(): void
    {
        $serviceRequest = $this->createServiceRequestAt($this->at('2026-07-01T00:00:00Z'));
        $this->assignAt($serviceRequest, $this->technician, $this->at('2026-07-01T01:00:00Z'));
        $this->resolveAt($serviceRequest, $this->technician, $this->at('2026-07-02T00:00:00Z'));
        $this->changeStatusAt($serviceRequest, $this->technician, ServiceRequestStatus::InProgress, $this->at('2026-07-05T00:00:00Z'));

        $trends = $this->trendsByMonth();

        $this->assertSame(0, array_sum(array_column($trends, 'resolved')));
        $this->assertNull($trends['2026-07']['average_resolution_hours']);
    }

    public function test_average_resolution_hours_cover_the_requests_resolved_in_the_month(): void
    {
        $oneDay = $this->createServiceRequestAt($this->at('2026-03-01T00:00:00Z'));
        $this->assignAt($oneDay, $this->technician, $this->at('2026-03-01T01:00:00Z'));
        $this->resolveAt($oneDay, $this->technician, $this->at('2026-03-02T00:00:00Z'));

        $twoDays = $this->createServiceRequestAt($this->at('2026-03-10T00:00:00Z'));
        $this->assignAt($twoDays, $this->technician, $this->at('2026-03-10T01:00:00Z'));
        $this->resolveAt($twoDays, $this->technician, $this->at('2026-03-12T00:00:00Z'));

        $trends = $this->trendsByMonth();

        $this->assertSame(2, $trends['2026-03']['resolved']);
        $this->assertEquals(36, $trends['2026-03']['average_resolution_hours']);
        $this->assertNull($trends['2026-02']['average_resolution_hours']);
        $this->assertNull($trends['2026-04']['average_resolution_hours']);
    }

    public function test_months_follow_the_reporting_time_zone(): void
    {
        config(['overview.time_zone' => self::COLOMBO]);

        $this->createServiceRequestAt($this->at('2026-10-31T18:00:00Z'));
        $this->createServiceRequestAt($this->at('2026-10-31T19:00:00Z'));

        $data = $this->fetchOverview();
        $trends = array_column($data['monthly_trends'], null, 'month');

        $this->assertSame(self::COLOMBO, $data['time_zone']);
        $this->assertSame(1, $trends['2026-10']['created']);
        $this->assertSame(1, $trends['2026-11']['created']);
    }

    public function test_tag_counts_cover_requests_created_in_the_window(): void
    {
        $this->createServiceRequestAt($this->daysAgo(10), ServiceRequestTag::Hardware, ServiceRequestTag::Network);
        $this->createServiceRequestAt($this->at('2025-11-15T12:00:00Z'), ServiceRequestTag::Software);

        $this->assertSame([
            ['tag' => ServiceRequestTag::Hardware->value, 'count' => 1],
            ['tag' => ServiceRequestTag::Software->value, 'count' => 0],
            ['tag' => ServiceRequestTag::Network->value, 'count' => 1],
            ['tag' => ServiceRequestTag::Other->value, 'count' => 0],
        ], $this->fetchOverview()['tag_counts']);
    }

    private function trendsByMonth(): array
    {
        return array_column($this->fetchOverview()['monthly_trends'], null, 'month');
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time);
    }
}
