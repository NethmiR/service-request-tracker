<?php

// Handles listing the technicians and admins a service request can be reassigned to.
// Returns each staff user as a short reference with only the id and the name.

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserReferenceResource;
use App\Services\ServiceRequests\ServiceRequestService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListServiceRequestAssigneesController extends Controller
{
    public function __invoke(ServiceRequestService $service): AnonymousResourceCollection
    {
        return UserReferenceResource::collection($service->listAssignableUsers());
    }
}
