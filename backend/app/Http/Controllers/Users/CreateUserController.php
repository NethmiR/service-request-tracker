<?php

// Handles an admin creating a user with a generated temporary password.
// Delegates to the user management service and returns the user with the password to display once.

namespace App\Http\Controllers\Users;

use App\Data\Auth\RequestContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Resources\IssuedCredentialsResource;
use App\Services\Users\UserManagementService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateUserController extends Controller
{
    public function __invoke(CreateUserRequest $request, UserManagementService $service): JsonResponse
    {
        $credentials = $service->createUser($request->user(), $request->toNewUser(), RequestContext::fromRequest($request));

        return (new IssuedCredentialsResource($credentials))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
