<?php

// Handles showing the overview page figures to technicians and admins.
// The view overview Gate is applied as route middleware, and the figures are built by the overview service.

namespace App\Http\Controllers\Overview;

use App\Http\Controllers\Controller;
use App\Http\Resources\OverviewResource;
use App\Services\Overview\OverviewService;
use Illuminate\Http\Request;

final class ShowOverviewController extends Controller
{
    public function __invoke(Request $request, OverviewService $service): OverviewResource
    {
        return new OverviewResource($service->build($request->user()));
    }
}
