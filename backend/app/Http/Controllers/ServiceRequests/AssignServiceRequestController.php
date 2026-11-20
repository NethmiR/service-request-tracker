<?php

// Handles a technician or admin assigning an open service request to themselves.
// Delegates to the workflow service and returns the updated request with its histories.

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\AssignServiceRequestRequest;
use App\Http\Resources\ServiceRequestDetailResource;
use App\Models\ServiceRequest;
use App\Services\ServiceRequests\ServiceRequestWorkflowService;

final class AssignServiceRequestController extends Controller
{
    public function __invoke(
        AssignServiceRequestRequest $request,
        ServiceRequest $serviceRequest,
        ServiceRequestWorkflowService $workflow,
    ): ServiceRequestDetailResource {
        return new ServiceRequestDetailResource(
            $workflow->assignToSelf($request->user(), $serviceRequest, $request->toSelfAssignment()),
        );
    }
}
