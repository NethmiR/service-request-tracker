<?php

// Handles the current assignee reassigning a service request to another technician or admin.
// Checks the reassign policy, delegates to the workflow service and returns the updated request.

namespace App\Http\Controllers\ServiceRequests;

use App\Enums\PolicyAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\ReassignServiceRequestRequest;
use App\Http\Resources\ServiceRequestDetailResource;
use App\Models\ServiceRequest;
use App\Services\ServiceRequests\ServiceRequestWorkflowService;
use Illuminate\Support\Facades\Gate;

final class ReassignServiceRequestController extends Controller
{
    public function __invoke(
        ReassignServiceRequestRequest $request,
        ServiceRequest $serviceRequest,
        ServiceRequestWorkflowService $workflow,
    ): ServiceRequestDetailResource {
        Gate::authorize(PolicyAbility::Reassign->value, $serviceRequest);

        return new ServiceRequestDetailResource(
            $workflow->reassign($request->user(), $serviceRequest, $request->toReassignment()),
        );
    }
}
