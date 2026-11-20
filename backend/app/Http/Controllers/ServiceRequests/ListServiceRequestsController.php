<?php

// Handles listing every service request on the dashboard for technicians and admins.
// Delegates to the service request service and returns one page through the summary resource.

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestSummaryResource;
use App\Services\ServiceRequests\ServiceRequestService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListServiceRequestsController extends Controller
{
    public function __invoke(ServiceRequestService $service): AnonymousResourceCollection
    {
        return ServiceRequestSummaryResource::collection($service->paginateAllRequests());
    }
}
