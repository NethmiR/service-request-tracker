<?php

// Verifies that the workflow service re-validates the stored state and writes atomically, on the SQLite test database.
// The service is called with models loaded before another change committed, which is what a request in flight holds.

namespace Tests\Feature\ServiceRequests;

use App\Data\ServiceRequests\Reassignment;
use App\Data\ServiceRequests\SelfAssignment;
use App\Data\ServiceRequests\StatusChange;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\ConcurrentUpdateException;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAssigneeHistory;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
use App\Services\ServiceRequests\ServiceRequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class WorkflowConsistencyTest extends TestCase
{
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string REMARK = 'Taking this request.';

    private const string FAILURE_MESSAGE = 'Forced failure while writing history.';

    public function test_self_assignment_with_a_stale_open_instance_is_rejected(): void
    {
        $firstTechnician = User::factory()->technician()->create();
        $secondTechnician = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Open);
        $stale = ServiceRequest::query()->findOrFail($serviceRequest->id);

        $this->assignThroughWorkflow($serviceRequest, $firstTechnician);

        $this->expectException(ConcurrentUpdateException::class);

        try {
            $this->workflow()->assignToSelf($secondTechnician, $stale, new SelfAssignment(self::REMARK));
        } finally {
            $this->assertSame(1, ServiceRequestAssigneeHistory::query()->count());
            $this->assertSame(2, ServiceRequestStatusHistory::query()->count());
        }
    }

    public function test_status_change_with_a_stale_instance_is_rejected_after_a_reassignment(): void
    {
        [$assignee, $newAssignee, $stale] = $this->staleRequestAfterReassignment();

        try {
            $this->workflow()->changeStatus($assignee, $stale, new StatusChange(ServiceRequestStatus::InProgress, null));
            $this->fail('The stale status change was accepted.');
        } catch (ConcurrentUpdateException) {
            $this->assertSame(2, ServiceRequestAssigneeHistory::query()->count());
            $this->assertSame(2, ServiceRequestStatusHistory::query()->count());
            $this->assertSame(ServiceRequestStatus::Assigned, $stale->refresh()->status);
            $this->assertTrue($stale->isAssignedTo($newAssignee));
        }
    }

    public function test_reassignment_with_a_stale_instance_is_rejected_after_a_reassignment(): void
    {
        [$assignee, , $stale] = $this->staleRequestAfterReassignment();
        $thirdTechnician = User::factory()->technician()->create();

        try {
            $this->workflow()->reassign($assignee, $stale, new Reassignment($thirdTechnician->id, self::REMARK));
            $this->fail('The stale reassignment was accepted.');
        } catch (ConcurrentUpdateException) {
            $this->assertSame(2, ServiceRequestAssigneeHistory::query()->count());
        }
    }

    public function test_failure_while_writing_the_status_record_rolls_back_the_assignee_record(): void
    {
        $technician = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Open);
        $event = 'eloquent.creating: '.ServiceRequestStatusHistory::class;

        ServiceRequestStatusHistory::creating(function (): never {
            throw new RuntimeException(self::FAILURE_MESSAGE);
        });

        try {
            $this->workflow()->assignToSelf($technician, $serviceRequest, new SelfAssignment(self::REMARK));
            $this->fail('The assignment succeeded although writing the status record failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(self::FAILURE_MESSAGE, $exception->getMessage());
        } finally {
            ServiceRequestStatusHistory::getEventDispatcher()->forget($event);
        }

        $this->assertSame(0, ServiceRequestAssigneeHistory::query()->count());
        $this->assertSame(1, ServiceRequestStatusHistory::query()->count());
        $this->assertSame(ServiceRequestStatus::Open, $serviceRequest->refresh()->status);
    }

    private function staleRequestAfterReassignment(): array
    {
        $assignee = User::factory()->technician()->create();
        $newAssignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);
        $stale = ServiceRequest::query()->findOrFail($serviceRequest->id);

        $this->assertTrue($stale->isAssignedTo($assignee));

        $this->workflow()->reassign($assignee, $serviceRequest, new Reassignment($newAssignee->id, self::REMARK));

        return [$assignee, $newAssignee, $stale];
    }

    private function workflow(): ServiceRequestWorkflowService
    {
        return app(ServiceRequestWorkflowService::class);
    }
}
