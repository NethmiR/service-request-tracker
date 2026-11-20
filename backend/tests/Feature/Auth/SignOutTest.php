<?php

// Verifies the sign out endpoint.
// The session row is removed, the event is audited and the user is no longer authenticated afterwards.

namespace Tests\Feature\Auth;

use App\Enums\AuthAuditEvent;
use App\Enums\ErrorCode;
use App\Models\AuthAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class SignOutTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string SIGN_OUT_ROUTE = '/api/auth/sign-out';

    private const string CURRENT_USER_ROUTE = '/api/auth/user';

    public function test_signing_out_ends_the_session_and_is_audited(): void
    {
        $user = User::factory()->create();

        $sessionId = $this->signInThroughApi($user)
            ->assertOk()
            ->getCookie(config('session.cookie'))
            ->getValue();

        $this->postJson(self::SIGN_OUT_ROUTE)->assertNoContent();

        $this->assertDatabaseMissing(config('session.table'), ['id' => $sessionId]);

        $log = AuthAuditLog::query()->where('event', AuthAuditEvent::SignedOut)->sole();

        $this->assertSame($user->id, $log->actor_user_id);
    }

    public function test_user_is_unauthenticated_after_signing_out(): void
    {
        $user = User::factory()->create();

        $this->signInThroughApi($user)->assertOk();
        $this->getJson(self::CURRENT_USER_ROUTE)->assertOk();

        $this->postJson(self::SIGN_OUT_ROUTE)->assertNoContent();

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    public function test_signing_out_without_a_session_is_unauthenticated(): void
    {
        $this->postJson(self::SIGN_OUT_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }
}
