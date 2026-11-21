<?php

// Builds service request history at chosen times for tests that depend on when each change happened.
// Each helper moves the clock with travelTo, calls the real workflow service and then returns the clock to where it was.

namespace Tests\Concerns;

use App\Data\ServiceRequests\Reassignment;
use App\Data\ServiceRequests\SelfAssignment;
use App\Data\ServiceRequests\StatusChange;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ServiceRequests\ServiceRequestWorkflowService;
use Carbon\CarbonImmutable;
use Closure;

trait BuildsWorkflowHistory
{
    private const string WORKFLOW_HISTORY_REMARK = 'Recorded at a chosen time for this test.';

    private ?User $workflowHistoryRequester = null;

    protected function createServiceRequestAt(CarbonImmutable $time, ServiceRequestTag ...$tags): ServiceRequest
    {
        return $this->atTime($time, function () use ($time, $tags): ServiceRequest {
            $factory = ServiceRequest::factory()
                ->requestedBy($this->workflowHistoryRequester())
                ->createdAt($time);

            return ($tags === [] ? $factory : $factory->withTags(...$tags))->create();
        });
    }

    protected function assignAt(ServiceRequest $serviceRequest, User $assignee, CarbonImmutable $time): void
    {
        $this->atTime($time, fn (): ServiceRequest => $this->workflowService()->assignToSelf(
            $assignee,
            $serviceRequest,
            new SelfAssignment(self::WORKFLOW_HISTORY_REMARK),
        ));
    }

    protected function reassignAt(ServiceRequest $serviceRequest, User $from, User $to, CarbonImmutable $time): void
    {
        $this->atTime($time, fn (): ServiceRequest => $this->workflowService()->reassign(
            $from,
            $serviceRequest,
            new Reassignment($to->id, self::WORKFLOW_HISTORY_REMARK),
        ));
    }

    protected function changeStatusAt(
        ServiceRequest $serviceRequest,
        User $actor,
        ServiceRequestStatus $status,
        CarbonImmutable $time,
    ): void {
        $this->atTime($time, fn (): ServiceRequest => $this->workflowService()->changeStatus(
            $actor,
            $serviceRequest,
            new StatusChange($status, null),
        ));
    }

    protected function resolveAt(ServiceRequest $serviceRequest, User $assignee, CarbonImmutable $time): void
    {
        $this->changeStatusAt($serviceRequest, $assignee, ServiceRequestStatus::Resolved, $time);
    }

    private function atTime(CarbonImmutable $time, Closure $action): mixed
    {
        $currentTime = CarbonImmutable::now();

        $this->travelTo($time);

        try {
            return $action();
        } finally {
            $this->travelTo($currentTime);
        }
    }

    private function workflowService(): ServiceRequestWorkflowService
    {
        return app(ServiceRequestWorkflowService::class);
    }

    private function workflowHistoryRequester(): User
    {
        return $this->workflowHistoryRequester ??= User::factory()->requester()->create();
    }
}
