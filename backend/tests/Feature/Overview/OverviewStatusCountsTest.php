<?php

// Verifies the overview count boxes for open, assigned and in progress requests.
// Resolved requests are never counted in any box.

namespace Tests\Feature\Overview;

use App\Enums\ServiceRequestStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowHistory;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithOverview;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class OverviewStatusCountsTest extends TestCase
{
    use BuildsWorkflowHistory;
    use InteractsWithFrontendSession;
    use InteractsWithOverview;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    public function test_counts_match_the_requests_in_each_counted_status(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $technician = User::factory()->technician()->create();

        foreach (range(1, 2) as $ignored) {
            $this->createServiceRequestAt($this->daysAgo(5));
        }

        foreach (range(1, 3) as $ignored) {
            $this->assignAt($this->createServiceRequestAt($this->daysAgo(5)), $technician, $this->daysAgo(4));
        }

        $inProgress = $this->createServiceRequestAt($this->daysAgo(5));
        $this->assignAt($inProgress, $technician, $this->daysAgo(4));
        $this->changeStatusAt($inProgress, $technician, ServiceRequestStatus::InProgress, $this->daysAgo(3));

        foreach (range(1, 4) as $ignored) {
            $resolved = $this->createServiceRequestAt($this->daysAgo(5));
            $this->assignAt($resolved, $technician, $this->daysAgo(4));
            $this->resolveAt($resolved, $technician, $this->daysAgo(3));
        }

        $this->assertSame([
            ['status' => ServiceRequestStatus::Open->value, 'count' => 2],
            ['status' => ServiceRequestStatus::Assigned->value, 'count' => 3],
            ['status' => ServiceRequestStatus::InProgress->value, 'count' => 1],
        ], $this->fetchOverview()['status_counts']);
    }
}
