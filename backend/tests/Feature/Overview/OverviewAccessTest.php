<?php

// Verifies who can reach the overview endpoint.
// Guests, users with a pending password change and requesters are rejected, while technicians and admins are served.

namespace Tests\Feature\Overview;

use App\Enums\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithOverview;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class OverviewAccessTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithOverview;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    public static function staffRoles(): array
    {
        return [
            'admin' => ['admin'],
            'technician' => ['technician'],
        ];
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->requestOverview()
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    #[DataProvider('staffRoles')]
    public function test_staff_with_a_pending_password_change_must_change_it_first(string $role): void
    {
        $this->signInAs(User::factory()->{$role}()->withPendingPasswordChange()->create());

        $this->requestOverview()
            ->assertForbidden()
            ->assertJsonPath('code', ErrorCode::PasswordChangeRequired->value);
    }

    public function test_requester_is_forbidden(): void
    {
        $this->signInAs(User::factory()->requester()->create());

        $this->requestOverview()
            ->assertForbidden()
            ->assertJsonPath('code', ErrorCode::Forbidden->value);
    }

    #[DataProvider('staffRoles')]
    public function test_staff_receive_the_overview(string $role): void
    {
        $this->signInAs(User::factory()->{$role}()->create());

        $this->requestOverview()->assertOk();
    }
}
