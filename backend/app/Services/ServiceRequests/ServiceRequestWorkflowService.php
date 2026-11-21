<?php

// Holds every workflow rule that depends on stored state, namely self assignment, reassignment and status changes.
// Each change locks the request row, re-validates its latest status and assignee and writes its history in one transaction.
// A change that waits longer than the configured lock timeout for the row lock is rejected as a concurrent update.

namespace App\Services\ServiceRequests;

use App\Data\ServiceRequests\Reassignment;
use App\Data\ServiceRequests\SelfAssignment;
use App\Data\ServiceRequests\StatusChange;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\AssigneeUnchangedException;
use App\Exceptions\ConcurrentUpdateException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\StatusUnchangedException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Database\LockTimeout;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ServiceRequestWorkflowService
{
    public function __construct(
        private readonly ServiceRequestHistoryRecorder $historyRecorder,
        private readonly ServiceRequestService $serviceRequestService,
        #[Config('workflow.lock_timeout_milliseconds')]
        private readonly int $lockTimeoutMilliseconds,
    ) {}

    public function assignToSelf(User $actor, ServiceRequest $serviceRequest, SelfAssignment $assignment): ServiceRequest
    {
        $this->runLocked(function () use ($actor, $serviceRequest, $assignment): void {
            $locked = $this->lockForChange($serviceRequest);

            if (! $locked->status->isAssignable()) {
                throw new ConcurrentUpdateException;
            }

            $this->historyRecorder->recordAssigneeChange($locked, null, $actor, $assignment->remark);
            $this->historyRecorder->recordStatusChange(
                $locked,
                ServiceRequestStatus::Open,
                ServiceRequestStatus::Assigned,
                $actor,
                null,
            );

            $locked->status = ServiceRequestStatus::Assigned;
            $locked->save();
        });

        return $this->freshDetail($serviceRequest);
    }

    public function reassign(User $actor, ServiceRequest $serviceRequest, Reassignment $reassignment): ServiceRequest
    {
        $this->runLocked(function () use ($actor, $serviceRequest, $reassignment): void {
            $locked = $this->lockForChange($serviceRequest);

            if (! $locked->isAssignedTo($actor)) {
                throw new ConcurrentUpdateException;
            }

            $newAssignee = User::query()->findOrFail($reassignment->assigneeId);

            if ($newAssignee->is($locked->currentAssignee())) {
                throw new AssigneeUnchangedException;
            }

            $this->historyRecorder->recordAssigneeChange($locked, $actor, $newAssignee, $reassignment->remark);

            $locked->touch();
        });

        return $this->freshDetail($serviceRequest);
    }

    public function changeStatus(User $actor, ServiceRequest $serviceRequest, StatusChange $change): ServiceRequest
    {
        $this->runLocked(function () use ($actor, $serviceRequest, $change): void {
            $locked = $this->lockForChange($serviceRequest);

            if (! $locked->isAssignedTo($actor)) {
                throw new ConcurrentUpdateException;
            }

            if ($locked->status === $change->status) {
                throw new StatusUnchangedException;
            }

            if (! $locked->status->canTransitionTo($change->status)) {
                throw new InvalidStatusTransitionException;
            }

            $this->historyRecorder->recordStatusChange(
                $locked,
                $locked->status,
                $change->status,
                $actor,
                $change->remark,
            );

            $locked->status = $change->status;
            $locked->save();
        });

        return $this->freshDetail($serviceRequest);
    }

    private function runLocked(Closure $change): void
    {
        try {
            DB::transaction(function () use ($change): void {
                LockTimeout::applyToCurrentTransaction($this->lockTimeoutMilliseconds);

                $change();
            });
        } catch (QueryException $exception) {
            if (LockTimeout::isLockTimeout($exception)) {
                throw new ConcurrentUpdateException($exception);
            }

            throw $exception;
        }
    }

    private function lockForChange(ServiceRequest $serviceRequest): ServiceRequest
    {
        $locked = ServiceRequest::query()
            ->whereKey($serviceRequest->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked->load('currentAssigneeHistory.assignedTo');
    }

    private function freshDetail(ServiceRequest $serviceRequest): ServiceRequest
    {
        return $this->serviceRequestService->loadDetail($serviceRequest->fresh());
    }
}
