<?php

// Writes every service request history record and is the only class allowed to do so.
// It never opens a transaction, because each caller already holds one.

namespace App\Services\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAssigneeHistory;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;

final class ServiceRequestHistoryRecorder
{
    public function recordCreation(ServiceRequest $serviceRequest): ServiceRequestStatusHistory
    {
        $record = new ServiceRequestStatusHistory([
            'from_status' => null,
            'to_status' => $serviceRequest->status,
            'changed_by_user_id' => null,
            'remark' => null,
        ]);

        $record->serviceRequest()->associate($serviceRequest);
        $record->save();

        return $record;
    }

    public function recordStatusChange(
        ServiceRequest $serviceRequest,
        ServiceRequestStatus $fromStatus,
        ServiceRequestStatus $toStatus,
        User $actor,
        ?string $remark,
    ): ServiceRequestStatusHistory {
        $record = new ServiceRequestStatusHistory([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'remark' => $remark,
        ]);

        $record->serviceRequest()->associate($serviceRequest);
        $record->changedBy()->associate($actor);
        $record->save();

        return $record;
    }

    public function recordAssigneeChange(
        ServiceRequest $serviceRequest,
        ?User $previousAssignee,
        User $newAssignee,
        string $remark,
    ): ServiceRequestAssigneeHistory {
        $record = new ServiceRequestAssigneeHistory([
            'remark' => $remark,
        ]);

        $record->serviceRequest()->associate($serviceRequest);
        $record->assignedFrom()->associate($previousAssignee);
        $record->assignedTo()->associate($newAssignee);
        $record->save();

        return $record;
    }
}
