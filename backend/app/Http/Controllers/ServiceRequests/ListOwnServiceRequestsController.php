<?php

// Handles listing the service requests raised by the signed in user.
// Delegates to the service request service and returns one page through the summary resource.

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\ListServiceRequestsRequest;
use App\Http\Resources\ServiceRequestSummaryResource;
use App\Services\ServiceRequests\ServiceRequestService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListOwnServiceRequestsController extends Controller
{
    public function __invoke(ListServiceRequestsRequest $request, ServiceRequestService $service): AnonymousResourceCollection
    {
        return ServiceRequestSummaryResource::collection(
            $service->paginateRequestsOf($request->user(), $request->toFilters())->appends($request->filterQuery()),
        );
    }
}
