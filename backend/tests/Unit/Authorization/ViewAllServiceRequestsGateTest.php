<?php

// Verifies the view all service requests Gate used by the request policy and the Phase 6 dashboard.
// Admins and technicians pass it, while requesters are denied.

namespace Tests\Unit\Authorization;

use App\Enums\GateAbility;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ViewAllServiceRequestsGateTest extends TestCase
{
    public function test_admin_may_view_all_service_requests(): void
    {
        $this->assertTrue(
            Gate::forUser(User::factory()->admin()->make())->allows(GateAbility::ViewAllServiceRequests->value),
        );
    }

    public function test_technician_may_view_all_service_requests(): void
    {
        $this->assertTrue(
            Gate::forUser(User::factory()->technician()->make())->allows(GateAbility::ViewAllServiceRequests->value),
        );
    }

    public function test_requester_may_not_view_all_service_requests(): void
    {
        $this->assertTrue(
            Gate::forUser(User::factory()->requester()->make())->denies(GateAbility::ViewAllServiceRequests->value),
        );
    }
}
