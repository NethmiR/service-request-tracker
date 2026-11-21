<?php

// Verifies the shape of the overview response, its figures on an empty database and the fields it never exposes.
// Also proves the number of queries stays the same however many requests the highlight lists hold.

namespace Tests\Feature\Overview;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Models\User;
use App\Services\Overview\OverviewHighlightsRepository;
use App\Services\Overview\ReportingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorkflowHistory;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithOverview;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class OverviewResponseTest extends TestCase
{
    use BuildsWorkflowHistory;
    use InteractsWithFrontendSession;
    use InteractsWithOverview;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const array OVERVIEW_FIELDS = [
        'generated_at',
        'time_zone',
        'status_counts',
        'highlights',
        'monthly_trends',
        'tag_counts',
        'staff_workload',
    ];

    private const array HIGHLIGHT_FIELDS = [
        'stale_after_days',
        'oldest_open',
        'assigned_to_viewer',
        'stale',
        'recently_resolved',
    ];

    private const array HIGHLIGHT_LISTS = [
        'oldest_open',
        'assigned_to_viewer',
        'stale',
        'recently_resolved',
    ];

    private const array HIGHLIGHT_ITEM_FIELDS = [
        'id',
        'title',
        'status',
        'requested_by',
        'assignee',
        'created_at',
        'last_status_changed_at',
    ];

    private const array FORBIDDEN_FIELDS = [
        '"email"',
        '"requested_by_user_id"',
        '"assigned_to_user_id"',
    ];

    private const string UTC_TIMESTAMP_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    private const int REQUESTS_PER_LIST_FOR_QUERY_COUNT = 15;

    public function test_response_holds_exactly_the_overview_fields(): void
    {
        $this->signInAs(User::factory()->technician()->create());

        $data = $this->fetchOverview();

        $this->assertSame(self::OVERVIEW_FIELDS, array_keys($data));
        $this->assertSame(self::HIGHLIGHT_FIELDS, array_keys($data['highlights']));
        $this->assertSame(self::OVERVIEW_NOW, $data['generated_at']);
        $this->assertSame(self::DEFAULT_REPORTING_TIME_ZONE, $data['time_zone']);
        $this->assertSame(self::DEFAULT_STALE_AFTER_DAYS, $data['highlights']['stale_after_days']);
    }

    public function test_each_highlight_item_holds_exactly_its_fields_with_utc_timestamps(): void
    {
        $viewer = $this->signInAs(User::factory()->technician()->create());
        $this->addHighlightFixtures($viewer, User::factory()->technician()->create());

        $highlights = $this->fetchOverview()['highlights'];

        foreach (self::HIGHLIGHT_LISTS as $list) {
            $this->assertCount(1, $highlights[$list], $list);

            $item = $highlights[$list][0];

            $this->assertSame(self::HIGHLIGHT_ITEM_FIELDS, array_keys($item), $list);
            $this->assertSame(['id', 'name'], array_keys($item['requested_by']), $list);
            $this->assertMatchesRegularExpression(self::UTC_TIMESTAMP_PATTERN, $item['created_at']);
            $this->assertMatchesRegularExpression(self::UTC_TIMESTAMP_PATTERN, $item['last_status_changed_at']);
        }

        $this->assertNull($highlights['oldest_open'][0]['assignee']);
        $this->assertSame(['id', 'name'], array_keys($highlights['assigned_to_viewer'][0]['assignee']));
    }

    public function test_empty_database_returns_zero_figures(): void
    {
        $viewer = $this->signInAs(User::factory()->technician()->create());

        $data = $this->fetchOverview();

        $this->assertSame([
            ['status' => ServiceRequestStatus::Open->value, 'count' => 0],
            ['status' => ServiceRequestStatus::Assigned->value, 'count' => 0],
            ['status' => ServiceRequestStatus::InProgress->value, 'count' => 0],
        ], $data['status_counts']);

        foreach (self::HIGHLIGHT_LISTS as $list) {
            $this->assertSame([], $data['highlights'][$list], $list);
        }

        $this->assertCount(ReportingCalendar::MONTH_COUNT, $data['monthly_trends']);

        foreach ($data['monthly_trends'] as $trend) {
            $this->assertSame(0, $trend['created']);
            $this->assertSame(0, $trend['resolved']);
            $this->assertNull($trend['average_resolution_hours']);
        }

        $this->assertSame(
            array_map(fn (ServiceRequestTag $tag): array => ['tag' => $tag->value, 'count' => 0], ServiceRequestTag::cases()),
            $data['tag_counts'],
        );
        $this->assertSame(
            [['user' => ['id' => $viewer->id, 'name' => $viewer->name], 'assigned' => 0, 'in_progress' => 0]],
            $data['staff_workload'],
        );
    }

    public function test_response_never_exposes_emails_or_internal_user_columns(): void
    {
        $viewer = $this->signInAs(User::factory()->admin()->create());
        $otherTechnician = User::factory()->technician()->create();
        $this->addHighlightFixtures($viewer, $otherTechnician);

        $content = (string) $this->requestOverview()->assertOk()->getContent();

        foreach (self::FORBIDDEN_FIELDS as $field) {
            $this->assertStringNotContainsString($field, $content);
        }

        foreach (User::query()->pluck('email') as $email) {
            $this->assertStringNotContainsString($email, $content);
        }
    }

    public function test_query_count_does_not_grow_with_the_number_of_requests(): void
    {
        $viewer = $this->signInAs(User::factory()->technician()->create());
        $otherTechnician = User::factory()->technician()->create();

        $this->addHighlightFixtures($viewer, $otherTechnician);
        $queriesForOneRequestPerList = $this->countQueriesForOverview(1);

        foreach (range(2, self::REQUESTS_PER_LIST_FOR_QUERY_COUNT) as $ignored) {
            $this->addHighlightFixtures($viewer, $otherTechnician);
        }
        $queriesForManyRequestsPerList = $this->countQueriesForOverview(OverviewHighlightsRepository::HIGHLIGHT_LIMIT);

        $this->assertSame($queriesForOneRequestPerList, $queriesForManyRequestsPerList);
    }

    private function addHighlightFixtures(User $viewer, User $otherTechnician): void
    {
        $this->createServiceRequestAt($this->daysAgo(1), ServiceRequestTag::Hardware);

        $assignedToViewer = $this->createServiceRequestAt($this->daysAgo(2), ServiceRequestTag::Software);
        $this->assignAt($assignedToViewer, $viewer, $this->daysAgo(1));

        $stale = $this->createServiceRequestAt($this->daysAgo(30), ServiceRequestTag::Network);
        $this->assignAt($stale, $otherTechnician, $this->daysAgo(20));

        $resolved = $this->createServiceRequestAt($this->daysAgo(3), ServiceRequestTag::Other);
        $this->assignAt($resolved, $otherTechnician, $this->daysAgo(2));
        $this->resolveAt($resolved, $otherTechnician, $this->daysAgo(1));
    }

    private function countQueriesForOverview(int $expectedItemsPerList): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->requestOverview()->assertOk();

        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        foreach (self::HIGHLIGHT_LISTS as $list) {
            $response->assertJsonCount($expectedItemsPerList, 'data.highlights.'.$list);
        }

        return $queryCount;
    }
}
