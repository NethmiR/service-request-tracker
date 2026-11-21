<?php

// Verifies the view overview Gate that guards the overview page figures.
// Admins and technicians pass it, while requesters are denied.

namespace Tests\Unit\Authorization;

use App\Enums\GateAbility;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ViewOverviewGateTest extends TestCase
{
    public function test_admin_may_view_the_overview(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->admin()->make())->allows(GateAbility::ViewOverview->value));
    }

    public function test_technician_may_view_the_overview(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->technician()->make())->allows(GateAbility::ViewOverview->value));
    }

    public function test_requester_may_not_view_the_overview(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->requester()->make())->denies(GateAbility::ViewOverview->value));
    }
}
