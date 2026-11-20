<?php

// Verifies the manage users Gate that guards every user management route.
// Only admins pass it, while technicians and requesters are denied.

namespace Tests\Unit\Authorization;

use App\Enums\GateAbility;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ManageUsersGateTest extends TestCase
{
    public function test_admin_may_manage_users(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->admin()->make())->allows(GateAbility::ManageUsers->value));
    }

    public function test_technician_may_not_manage_users(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->technician()->make())->denies(GateAbility::ManageUsers->value));
    }

    public function test_requester_may_not_manage_users(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->requester()->make())->denies(GateAbility::ManageUsers->value));
    }
}
