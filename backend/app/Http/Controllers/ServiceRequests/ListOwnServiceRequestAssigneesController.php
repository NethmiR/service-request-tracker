<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserReferenceResource;
use App\Services\ServiceRequests\ServiceRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListOwnServiceRequestAssigneesController extends Controller
{
    public function __invoke(Request $request, ServiceRequestService $service): AnonymousResourceCollection
    {
        return UserReferenceResource::collection($service->listCurrentAssigneesOfRequestsBy($request->user()));
    }
}
