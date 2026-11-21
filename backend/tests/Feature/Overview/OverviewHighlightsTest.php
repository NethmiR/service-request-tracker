<?php

// Verifies the four overview highlight lists, their rules, their order and their size limit.
// Covers the configurable stale threshold and the time of the latest status record shown on every item.

namespace Tests\Feature\Overview;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Overview\OverviewHighlightsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowHistory;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithOverview;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class OverviewHighlightsTest extends TestCase
{
    use BuildsWorkflowHistory;
    use InteractsWithFrontendSession;
    use InteractsWithOverview;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const int SHORTER_STALE_AFTER_DAYS = 3;

    public function test_oldest_open_lists_only_open_requests_oldest_first_up_to_the_limit(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $technician = User::factory()->technician()->create();

        $assigned = $this->createServiceRequestAt($this->daysAgo(40));
        $this->assignAt($assigned, $technician, $this->daysAgo(39));

        $openRequests = [];

        foreach (range(OverviewHighlightsRepository::HIGHLIGHT_LIMIT + 1, 1) as $days) {
            $openRequests[] = $this->createServiceRequestAt($this->daysAgo($days));
        }

        $oldestOpen = $this->fetchOverview()['highlights']['oldest_open'];

        $this->assertSame(
            array_map(fn (ServiceRequest $serviceRequest): int => $serviceRequest->id, array_slice($openRequests, 0, OverviewHighlightsRepository::HIGHLIGHT_LIMIT)),
            $this->idsOf($oldestOpen),
        );
        $this->assertSame(
            array_fill(0, OverviewHighlightsRepository::HIGHLIGHT_LIMIT, ServiceRequestStatus::Open->value),
            array_column($oldestOpen, 'status'),
        );
    }

    public function test_assigned_to_viewer_lists_only_the_viewers_unresolved_requests(): void
    {
        $viewer = $this->signInAs(User::factory()->technician()->create());
        $otherTechnician = User::factory()->technician()->create();

        $assigned = $this->createServiceRequestAt($this->daysAgo(10));
        $this->assignAt($assigned, $viewer, $this->daysAgo(9));

        $inProgress = $this->createServiceRequestAt($this->daysAgo(8));
        $this->assignAt($inProgress, $viewer, $this->daysAgo(7));
        $this->changeStatusAt($inProgress, $viewer, ServiceRequestStatus::InProgress, $this->daysAgo(6));

        $assignedToOther = $this->createServiceRequestAt($this->daysAgo(7));
        $this->assignAt($assignedToOther, $otherTechnician, $this->daysAgo(6));

        $resolved = $this->createServiceRequestAt($this->daysAgo(6));
        $this->assignAt($resolved, $viewer, $this->daysAgo(5));
        $this->resolveAt($resolved, $viewer, $this->daysAgo(4));

        $reassignedAway = $this->createServiceRequestAt($this->daysAgo(5));
        $this->assignAt($reassignedAway, $viewer, $this->daysAgo(4));
        $this->reassignAt($reassignedAway, $viewer, $otherTechnician, $this->daysAgo(3));

        $this->assertSame(
            [$assigned->id, $inProgress->id],
            $this->idsOf($this->fetchOverview()['highlights']['assigned_to_viewer']),
        );

        $this->switchToFreshClient();
        $this->signInAs($otherTechnician);

        $this->assertSame(
            [$assignedToOther->id, $reassignedAway->id],
            $this->idsOf($this->fetchOverview()['highlights']['assigned_to_viewer']),
        );
    }

    public function test_stale_lists_unresolved_requests_without_a_recent_status_change_longest_waiting_first(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $technician = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();

        $neverAssigned = $this->createServiceRequestAt($this->daysAgo(30));

        $reassignedRecently = $this->createServiceRequestAt($this->daysAgo(25));
        $this->assignAt($reassignedRecently, $technician, $this->daysAgo(20));
        $this->reassignAt($reassignedRecently, $technician, $otherTechnician, $this->daysAgo(1));

        $assignedLongAgo = $this->createServiceRequestAt($this->daysAgo(15));
        $this->assignAt($assignedLongAgo, $technician, $this->daysAgo(10));

        $changedRecently = $this->createServiceRequestAt($this->daysAgo(15));
        $this->assignAt($changedRecently, $technician, $this->daysAgo(10));
        $this->changeStatusAt($changedRecently, $technician, ServiceRequestStatus::InProgress, $this->daysAgo(2));

        $resolvedLongAgo = $this->createServiceRequestAt($this->daysAgo(30));
        $this->assignAt($resolvedLongAgo, $technician, $this->daysAgo(29));
        $this->resolveAt($resolvedLongAgo, $technician, $this->daysAgo(28));

        $stale = $this->fetchOverview()['highlights']['stale'];

        $this->assertSame([$neverAssigned->id, $reassignedRecently->id, $assignedLongAgo->id], $this->idsOf($stale));
        $this->assertSame($this->daysAgo(20)->toIso8601ZuluString(), $stale[1]['last_status_changed_at']);
    }

    public function test_stale_threshold_follows_the_configuration(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $technician = User::factory()->technician()->create();

        $serviceRequest = $this->createServiceRequestAt($this->daysAgo(10));
        $this->assignAt($serviceRequest, $technician, $this->daysAgo(5));

        $defaultHighlights = $this->fetchOverview()['highlights'];

        $this->assertSame(self::DEFAULT_STALE_AFTER_DAYS, $defaultHighlights['stale_after_days']);
        $this->assertSame([], $defaultHighlights['stale']);

        config(['overview.stale_after_days' => self::SHORTER_STALE_AFTER_DAYS]);

        $shorterHighlights = $this->fetchOverview()['highlights'];

        $this->assertSame(self::SHORTER_STALE_AFTER_DAYS, $shorterHighlights['stale_after_days']);
        $this->assertSame([$serviceRequest->id], $this->idsOf($shorterHighlights['stale']));
    }

    public function test_recently_resolved_lists_resolved_requests_most_recently_resolved_first(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $technician = User::factory()->technician()->create();

        $resolvedThreeDaysAgo = $this->createServiceRequestAt($this->daysAgo(30));
        $this->assignAt($resolvedThreeDaysAgo, $technician, $this->daysAgo(29));
        $this->resolveAt($resolvedThreeDaysAgo, $technician, $this->daysAgo(3));

        $resolvedYesterday = $this->createServiceRequestAt($this->daysAgo(30));
        $this->assignAt($resolvedYesterday, $technician, $this->daysAgo(29));
        $this->resolveAt($resolvedYesterday, $technician, $this->daysAgo(1));

        $reopened = $this->createServiceRequestAt($this->daysAgo(30));
        $this->assignAt($reopened, $technician, $this->daysAgo(29));
        $this->resolveAt($reopened, $technician, $this->daysAgo(10));
        $this->changeStatusAt($reopened, $technician, ServiceRequestStatus::Assigned, $this->daysAgo(2));

        $resolvedAgain = $this->createServiceRequestAt($this->daysAgo(30));
        $this->assignAt($resolvedAgain, $technician, $this->daysAgo(29));
        $this->resolveAt($resolvedAgain, $technician, $this->daysAgo(20));
        $this->changeStatusAt($resolvedAgain, $technician, ServiceRequestStatus::InProgress, $this->daysAgo(15));
        $this->resolveAt($resolvedAgain, $technician, $this->daysAgo(5));

        $recentlyResolved = $this->fetchOverview()['highlights']['recently_resolved'];

        $this->assertSame(
            [$resolvedYesterday->id, $resolvedThreeDaysAgo->id, $resolvedAgain->id],
            $this->idsOf($recentlyResolved),
        );
        $this->assertSame($this->daysAgo(5)->toIso8601ZuluString(), $recentlyResolved[2]['last_status_changed_at']);
    }

    public function test_last_status_change_is_the_time_of_the_latest_status_record(): void
    {
        $viewer = $this->signInAs(User::factory()->technician()->create());
        $otherTechnician = User::factory()->technician()->create();

        $neverAssigned = $this->createServiceRequestAt($this->daysAgo(2));

        $serviceRequest = $this->createServiceRequestAt($this->daysAgo(6));
        $this->assignAt($serviceRequest, $otherTechnician, $this->daysAgo(5));
        $this->changeStatusAt($serviceRequest, $otherTechnician, ServiceRequestStatus::InProgress, $this->daysAgo(4));
        $this->reassignAt($serviceRequest, $otherTechnician, $viewer, $this->daysAgo(3));

        $highlights = $this->fetchOverview()['highlights'];

        $this->assertSame([$neverAssigned->id], $this->idsOf($highlights['oldest_open']));
        $this->assertSame($this->daysAgo(2)->toIso8601ZuluString(), $highlights['oldest_open'][0]['last_status_changed_at']);

        $this->assertSame([$serviceRequest->id], $this->idsOf($highlights['assigned_to_viewer']));
        $this->assertSame($this->daysAgo(4)->toIso8601ZuluString(), $highlights['assigned_to_viewer'][0]['last_status_changed_at']);
        $this->assertSame($this->daysAgo(6)->toIso8601ZuluString(), $highlights['assigned_to_viewer'][0]['created_at']);
        $this->assertSame(
            ['id' => $viewer->id, 'name' => $viewer->name],
            $highlights['assigned_to_viewer'][0]['assignee'],
        );
    }
}
