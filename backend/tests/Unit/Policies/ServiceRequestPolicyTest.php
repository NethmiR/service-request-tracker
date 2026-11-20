<?php

// Verifies who may view a service request through the service request policy.
// The requester, technicians and admins may, while any other requester is told the request does not exist.

namespace Tests\Unit\Policies;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tests\TestCase;

class ServiceRequestPolicyTest extends TestCase
{
    private const int REQUESTER_ID = 1;

    private const int OTHER_USER_ID = 2;

    public function test_policy_is_registered_for_service_requests(): void
    {
        $this->assertInstanceOf(ServiceRequestPolicy::class, Gate::getPolicyFor(ServiceRequest::class));
    }

    public function test_requester_may_view_their_own_request(): void
    {
        $requester = User::factory()->requester()->make(['id' => self::REQUESTER_ID]);

        $this->assertTrue((new ServiceRequestPolicy)->view($requester, $this->requestOf(self::REQUESTER_ID))->allowed());
    }

    public function test_technician_may_view_another_users_request(): void
    {
        $technician = User::factory()->technician()->make(['id' => self::OTHER_USER_ID]);

        $this->assertTrue((new ServiceRequestPolicy)->view($technician, $this->requestOf(self::REQUESTER_ID))->allowed());
    }

    public function test_admin_may_view_another_users_request(): void
    {
        $admin = User::factory()->admin()->make(['id' => self::OTHER_USER_ID]);

        $this->assertTrue((new ServiceRequestPolicy)->view($admin, $this->requestOf(self::REQUESTER_ID))->allowed());
    }

    public function test_other_requester_is_denied_as_not_found(): void
    {
        $otherRequester = User::factory()->requester()->make(['id' => self::OTHER_USER_ID]);

        $response = (new ServiceRequestPolicy)->view($otherRequester, $this->requestOf(self::REQUESTER_ID));

        $this->assertTrue($response->denied());
        $this->assertSame(HttpResponse::HTTP_NOT_FOUND, $response->status());
    }

    private function requestOf(int $requesterId): ServiceRequest
    {
        return (new ServiceRequest)->forceFill(['requested_by_user_id' => $requesterId]);
    }
}
