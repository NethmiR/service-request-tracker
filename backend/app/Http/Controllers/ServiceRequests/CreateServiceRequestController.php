<?php

// Handles a signed in user raising a new service request.
// Delegates to the service request service and returns the created request with its histories.

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\CreateServiceRequestRequest;
use App\Http\Resources\ServiceRequestDetailResource;
use App\Services\ServiceRequests\ServiceRequestService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateServiceRequestController extends Controller
{
    public function __invoke(CreateServiceRequestRequest $request, ServiceRequestService $service): JsonResponse
    {
        $serviceRequest = $service->createRequest($request->user(), $request->toNewServiceRequest());

        return (new ServiceRequestDetailResource($serviceRequest))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
