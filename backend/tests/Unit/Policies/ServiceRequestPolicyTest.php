<?php

// Verifies who may view and work on a service request through the service request policy.
// Viewing follows ownership and role, while reassigning and changing the status are allowed only for the current assignee.

namespace Tests\Unit\Policies;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class ServiceRequestPolicyTest extends TestCase
{
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

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

    public static function workAbilities(): array
    {
        return [
            'reassign' => ['reassign'],
            'change status' => ['changeStatus'],
        ];
    }

    #[DataProvider('workAbilities')]
    public function test_current_assignee_may_work_on_the_request(string $ability): void
    {
        $technician = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $technician);

        $this->assertTrue((new ServiceRequestPolicy)->{$ability}($technician, $serviceRequest)->allowed());
    }

    #[DataProvider('workAbilities')]
    public function test_everyone_else_is_denied_with_forbidden(string $ability): void
    {
        $assignee = User::factory()->technician()->create();
        $previousAssignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $previousAssignee);
        $this->assignThroughHistory($serviceRequest, $assignee, $previousAssignee);
        $serviceRequest->unsetRelation('currentAssigneeHistory');

        $others = [
            $previousAssignee,
            User::factory()->technician()->create(),
            User::factory()->admin()->create(),
            User::factory()->requester()->create(),
        ];

        foreach ($others as $other) {
            $response = (new ServiceRequestPolicy)->{$ability}($other, $serviceRequest);

            $this->assertTrue($response->denied());
            $this->assertSame(HttpResponse::HTTP_FORBIDDEN, $response->status() ?? HttpResponse::HTTP_FORBIDDEN);
        }
    }

    #[DataProvider('workAbilities')]
    public function test_everyone_is_denied_on_an_open_request(string $ability): void
    {
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Open);

        foreach ([User::factory()->technician()->create(), User::factory()->admin()->create(), User::factory()->requester()->create()] as $user) {
            $this->assertTrue((new ServiceRequestPolicy)->{$ability}($user, $serviceRequest)->denied());
        }
    }

    private function requestOf(int $requesterId): ServiceRequest
    {
        return (new ServiceRequest)->forceFill(['requested_by_user_id' => $requesterId]);
    }
}
