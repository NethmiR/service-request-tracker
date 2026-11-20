<?php

// Handles viewing one service request with its status and assignee histories.
// Checks the service request policy, which hides requests the user may not view behind a not found response.

namespace App\Http\Controllers\ServiceRequests;

use App\Enums\PolicyAbility;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestDetailResource;
use App\Models\ServiceRequest;
use App\Services\ServiceRequests\ServiceRequestService;
use Illuminate\Support\Facades\Gate;

final class ShowServiceRequestController extends Controller
{
    public function __invoke(ServiceRequest $serviceRequest, ServiceRequestService $service): ServiceRequestDetailResource
    {
        Gate::authorize(PolicyAbility::View->value, $serviceRequest);

        return new ServiceRequestDetailResource($service->loadDetail($serviceRequest));
    }
}
