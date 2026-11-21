<?php

// Verifies the overview workload of every technician and admin.
// Covers who is listed, the name then id order, the assigned and in progress counts and the effect of a reassignment.

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

class OverviewWorkloadTest extends TestCase
{
    use BuildsWorkflowHistory;
    use InteractsWithFrontendSession;
    use InteractsWithOverview;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    public function test_every_staff_user_is_listed_by_name_then_id(): void
    {
        $viewer = $this->signInAs(User::factory()->technician()->create(['name' => 'Zed']));
        $alphaAdmin = User::factory()->admin()->create(['name' => 'Alpha']);
        $alphaTechnician = User::factory()->technician()->create(['name' => 'Alpha']);
        $beta = User::factory()->technician()->create(['name' => 'Beta']);
        $pending = User::factory()->technician()->withPendingPasswordChange()->create(['name' => 'Delta']);
        User::factory()->requester()->create(['name' => 'Aaron']);

        $workload = $this->fetchOverview()['staff_workload'];

        $this->assertSame(
            [$alphaAdmin->id, $alphaTechnician->id, $beta->id, $pending->id, $viewer->id],
            array_column(array_column($workload, 'user'), 'id'),
        );

        foreach ($workload as $item) {
            $this->assertSame(['user', 'assigned', 'in_progress'], array_keys($item));
            $this->assertSame(['id', 'name'], array_keys($item['user']));
            $this->assertSame(0, $item['assigned']);
            $this->assertSame(0, $item['in_progress']);
        }
    }

    public function test_counts_match_the_current_assigned_and_in_progress_requests(): void
    {
        $this->signInAs(User::factory()->technician()->create(['name' => 'Zed']));
        $admin = User::factory()->admin()->create(['name' => 'Alpha']);
        $technician = User::factory()->technician()->create(['name' => 'Beta']);

        foreach (range(1, 2) as $ignored) {
            $this->assignAt($this->createServiceRequestAt($this->daysAgo(5)), $technician, $this->daysAgo(4));
        }

        $technicianInProgress = $this->createServiceRequestAt($this->daysAgo(5));
        $this->assignAt($technicianInProgress, $technician, $this->daysAgo(4));
        $this->changeStatusAt($technicianInProgress, $technician, ServiceRequestStatus::InProgress, $this->daysAgo(3));

        $technicianResolved = $this->createServiceRequestAt($this->daysAgo(5));
        $this->assignAt($technicianResolved, $technician, $this->daysAgo(4));
        $this->resolveAt($technicianResolved, $technician, $this->daysAgo(3));

        $adminInProgress = $this->createServiceRequestAt($this->daysAgo(5));
        $this->assignAt($adminInProgress, $admin, $this->daysAgo(4));
        $this->changeStatusAt($adminInProgress, $admin, ServiceRequestStatus::InProgress, $this->daysAgo(3));

        $this->createServiceRequestAt($this->daysAgo(5));

        $workload = $this->workloadByUser();

        $this->assertSame(['assigned' => 0, 'in_progress' => 1], $workload[$admin->id]);
        $this->assertSame(['assigned' => 2, 'in_progress' => 1], $workload[$technician->id]);
    }

    public function test_a_reassigned_request_counts_only_for_the_new_assignee(): void
    {
        $this->signInAs(User::factory()->technician()->create(['name' => 'Zed']));
        $previousAssignee = User::factory()->technician()->create(['name' => 'Alpha']);
        $newAssignee = User::factory()->technician()->create(['name' => 'Beta']);

        $serviceRequest = $this->createServiceRequestAt($this->daysAgo(5));
        $this->assignAt($serviceRequest, $previousAssignee, $this->daysAgo(4));
        $this->changeStatusAt($serviceRequest, $previousAssignee, ServiceRequestStatus::InProgress, $this->daysAgo(3));
        $this->reassignAt($serviceRequest, $previousAssignee, $newAssignee, $this->daysAgo(2));

        $workload = $this->workloadByUser();

        $this->assertSame(['assigned' => 0, 'in_progress' => 0], $workload[$previousAssignee->id]);
        $this->assertSame(['assigned' => 0, 'in_progress' => 1], $workload[$newAssignee->id]);
    }

    private function workloadByUser(): array
    {
        $workload = [];

        foreach ($this->fetchOverview()['staff_workload'] as $item) {
            $workload[$item['user']['id']] = [
                'assigned' => $item['assigned'],
                'in_progress' => $item['in_progress'],
            ];
        }

        return $workload;
    }
}
