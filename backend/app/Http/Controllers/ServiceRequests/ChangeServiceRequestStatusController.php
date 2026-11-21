<?php

// Handles the current assignee changing the status of a service request.
// Checks the change status policy, delegates to the workflow service and returns the updated request.

namespace App\Http\Controllers\ServiceRequests;

use App\Enums\PolicyAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\ChangeServiceRequestStatusRequest;
use App\Http\Resources\ServiceRequestDetailResource;
use App\Models\ServiceRequest;
use App\Services\ServiceRequests\ServiceRequestWorkflowService;
use Illuminate\Support\Facades\Gate;

final class ChangeServiceRequestStatusController extends Controller
{
    public function __invoke(
        ChangeServiceRequestStatusRequest $request,
        ServiceRequest $serviceRequest,
        ServiceRequestWorkflowService $workflow,
    ): ServiceRequestDetailResource {
        Gate::authorize(PolicyAbility::ChangeStatus->value, $serviceRequest);

        return new ServiceRequestDetailResource(
            $workflow->changeStatus($request->user(), $serviceRequest, $request->toStatusChange()),
        );
    }
}
