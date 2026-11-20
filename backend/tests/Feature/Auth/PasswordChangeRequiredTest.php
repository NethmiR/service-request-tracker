<?php

// Verifies that the server blocks protected endpoints while a password change is pending.
// Only the current user, sign out and password change endpoints stay reachable.

namespace Tests\Feature\Auth;

use App\Enums\ErrorCode;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class PasswordChangeRequiredTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string PROTECTED_ROUTE = '/api/testing/protected';

    private const string NEW_PASSWORD = 'Brand-New-Password-2026!';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', EnsurePasswordChanged::class])
            ->get(self::PROTECTED_ROUTE, fn (): array => ['reached' => true]);
    }

    public function test_pending_user_is_rejected_with_the_dedicated_error_code(): void
    {
        $user = User::factory()->withPendingPasswordChange()->create();

        $this->signInThroughApi($user)->assertOk();

        $this->getJson(self::PROTECTED_ROUTE)
            ->assertForbidden()
            ->assertJsonPath('code', ErrorCode::PasswordChangeRequired->value);
    }

    public function test_user_who_changed_the_password_can_reach_the_route(): void
    {
        $user = User::factory()->create();

        $this->signInThroughApi($user)->assertOk();

        $this->getJson(self::PROTECTED_ROUTE)
            ->assertOk()
            ->assertExactJson(['reached' => true]);
    }

    public function test_pending_user_can_still_use_the_allowed_auth_endpoints(): void
    {
        $user = User::factory()->withPendingPasswordChange()->create();

        $this->signInThroughApi($user)->assertOk();

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.password_change_required', true);

        $changeResponse = $this->patchJson('/api/auth/password', [
            'current_password' => UserFactory::DEFAULT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->rememberSessionCookie($changeResponse);

        $this->postJson('/api/auth/sign-out')->assertNoContent();
    }
}
