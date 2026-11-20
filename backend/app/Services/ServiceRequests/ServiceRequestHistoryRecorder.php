<?php

// Writes every service request history record and is the only class allowed to do so.
// It never opens a transaction, because each caller already holds one.

namespace App\Services\ServiceRequests;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestStatusHistory;

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
}
