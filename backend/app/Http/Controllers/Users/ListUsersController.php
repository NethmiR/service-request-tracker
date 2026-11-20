<?php

// Handles listing users for the admin users page.
// Delegates to the user management service and returns one page of users through the user resource.

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Users\UserManagementService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListUsersController extends Controller
{
    public function __invoke(UserManagementService $service): AnonymousResourceCollection
    {
        return UserResource::collection($service->paginateUsers());
    }
}
