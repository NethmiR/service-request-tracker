<?php

// Verifies the handle service requests Gate that guards assignment, reassignment and status changes.
// Admins and technicians pass it, while requesters are denied.

namespace Tests\Unit\Authorization;

use App\Enums\GateAbility;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HandleServiceRequestsGateTest extends TestCase
{
    public function test_admin_may_handle_service_requests(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->admin()->make())->allows(GateAbility::HandleServiceRequests->value));
    }

    public function test_technician_may_handle_service_requests(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->technician()->make())->allows(GateAbility::HandleServiceRequests->value));
    }

    public function test_requester_may_not_handle_service_requests(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->requester()->make())->denies(GateAbility::HandleServiceRequests->value));
    }
}
