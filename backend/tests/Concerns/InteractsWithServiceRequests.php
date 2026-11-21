<?php

// Prepares service requests and their histories for feature tests.
// Assignments are written either through the history factories or through the real workflow service.

namespace Tests\Concerns;

use App\Data\ServiceRequests\SelfAssignment;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAssigneeHistory;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
use App\Services\ServiceRequests\ServiceRequestWorkflowService;
use Database\Factories\ServiceRequestFactory;

trait InteractsWithServiceRequests
{
    private const string ASSIGNMENT_REMARK = 'Taking this request.';

    protected function signInAs(User $user): User
    {
        $this->signInThroughApi($user)->assertOk();

        return $user;
    }

    protected function createServiceRequestFor(User $requester, ServiceRequestTag ...$tags): ServiceRequest
    {
        return $this->serviceRequestFactoryFor($requester, ...$tags)->create();
    }

    protected function serviceRequestFactoryFor(User $requester, ServiceRequestTag ...$tags): ServiceRequestFactory
    {
        $factory = ServiceRequest::factory()->requestedBy($requester);

        return $tags === [] ? $factory : $factory->withTags(...$tags);
    }

    protected function assignThroughHistory(
        ServiceRequest $serviceRequest,
        User $assignee,
        ?User $previousAssignee = null,
    ): void {
        $serviceRequest->refresh();

        ServiceRequestAssigneeHistory::factory()
            ->for($serviceRequest)
            ->assignedFrom($previousAssignee)
            ->assignedTo($assignee)
            ->withRemark(self::ASSIGNMENT_REMARK)
            ->create();

        if ($previousAssignee !== null) {
            return;
        }

        ServiceRequestStatusHistory::factory()
            ->for($serviceRequest)
            ->transition($serviceRequest->status, ServiceRequestStatus::Assigned, $assignee)
            ->create();

        $serviceRequest->forceFill(['status' => ServiceRequestStatus::Assigned])->save();
    }

    protected function createServiceRequestInStatus(ServiceRequestStatus $status, ?User $assignee = null): ServiceRequest
    {
        $serviceRequest = $this->createServiceRequestFor(User::factory()->requester()->create());

        if ($status === ServiceRequestStatus::Open) {
            return $serviceRequest;
        }

        $this->assignThroughWorkflow($serviceRequest, $assignee ?? User::factory()->technician()->create());
        $serviceRequest->refresh();

        if ($status !== ServiceRequestStatus::Assigned) {
            $serviceRequest->forceFill(['status' => $status])->save();
        }

        return $serviceRequest;
    }

    protected function assignThroughWorkflow(ServiceRequest $serviceRequest, User $assignee): ServiceRequest
    {
        return app(ServiceRequestWorkflowService::class)->assignToSelf(
            $assignee,
            $serviceRequest,
            new SelfAssignment(self::ASSIGNMENT_REMARK),
        );
    }
}
