<?php

// Prepares service requests and their histories for feature tests.
// Assignments are written through the history factories, standing in for the workflow that arrives in Phase 6.

namespace Tests\Concerns;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAssigneeHistory;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
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

        ServiceRequestStatusHistory::factory()
            ->for($serviceRequest)
            ->transition($serviceRequest->status, ServiceRequestStatus::Assigned, $previousAssignee ?? $assignee)
            ->create();

        $serviceRequest->forceFill(['status' => ServiceRequestStatus::Assigned])->save();
    }
}
