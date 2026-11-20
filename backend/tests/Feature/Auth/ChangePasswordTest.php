<?php

// Verifies the self service password change.
// Covers validation, the stored result, session regeneration, other session termination and rate limiting.

namespace Tests\Feature\Auth;

use App\Enums\AuthAuditEvent;
use App\Enums\ErrorCode;
use App\Models\AuthAuditLog;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string CHANGE_PASSWORD_ROUTE = '/api/auth/password';

    private const string CURRENT_USER_ROUTE = '/api/auth/user';

    private const string NEW_PASSWORD = 'Brand-New-Password-2026!';

    private const string WRONG_PASSWORD = 'Wrong-Password-2026!';

    public static function invalidNewPasswords(): array
    {
        return [
            'too short' => ['Short-Pa1!'],
            'no uppercase letter' => ['lowercase-only-pass1!'],
            'no symbol' => ['MissingSymbolPass2026'],
        ];
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->signInThroughApi(User::factory()->create());

        $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $this->payload(currentPassword: self::WRONG_PASSWORD))
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors(['current_password']);
    }

    #[DataProvider('invalidNewPasswords')]
    public function test_password_breaking_the_policy_is_rejected(string $password): void
    {
        $this->signInThroughApi(User::factory()->create());

        $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $this->payload(password: $password, confirmation: $password))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_confirmation_mismatch_is_rejected(): void
    {
        $this->signInThroughApi(User::factory()->create());

        $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $this->payload(confirmation: 'Different-Password-2026!'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_new_password_equal_to_the_current_one_is_rejected(): void
    {
        $this->signInThroughApi(User::factory()->create());

        $this->patchJson(
            self::CHANGE_PASSWORD_ROUTE,
            $this->payload(password: UserFactory::DEFAULT_PASSWORD, confirmation: UserFactory::DEFAULT_PASSWORD),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_successful_change_updates_the_user_and_is_audited(): void
    {
        $user = User::factory()->withPendingPasswordChange()->create();

        $this->signInThroughApi($user)->assertOk();

        $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $this->payload())
            ->assertOk()
            ->assertJsonPath('data.password_change_required', false);

        $user->refresh();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertNotNull($user->password_changed_at);

        $log = AuthAuditLog::query()->where('event', AuthAuditEvent::PasswordChanged)->sole();

        $this->assertSame($user->id, $log->actor_user_id);
    }

    public function test_current_session_is_regenerated_and_stays_signed_in(): void
    {
        $user = User::factory()->create();

        $oldSessionId = $this->signInThroughApi($user)
            ->getCookie(config('session.cookie'))
            ->getValue();

        $response = $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $this->payload())->assertOk();
        $newSessionId = $response->getCookie(config('session.cookie'))->getValue();

        $this->assertNotSame($oldSessionId, $newSessionId);
        $this->assertDatabaseMissing(config('session.table'), ['id' => $oldSessionId]);
        $this->assertDatabaseHas(config('session.table'), ['id' => $newSessionId, 'user_id' => $user->id]);

        $this->rememberSessionCookie($response);

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_every_other_session_of_the_user_is_terminated(): void
    {
        $user = User::factory()->create();

        $this->signInThroughApi($user)->assertOk();
        $firstClientCookie = $this->currentSessionCookieValue();

        $this->switchToFreshClient();
        $this->signInThroughApi($user)->assertOk();
        $secondClientCookie = $this->currentSessionCookieValue();

        $this->assertSame(2, $this->sessionCountFor($user));

        $this->switchToFreshClient();
        $this->useSessionCookieValue($firstClientCookie);
        $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $this->payload())->assertOk();

        $this->assertSame(1, $this->sessionCountFor($user));

        $this->switchToFreshClient();
        $this->useSessionCookieValue($secondClientCookie);

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    public function test_sessions_of_other_users_are_not_touched(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->signInThroughApi($otherUser)->assertOk();

        $this->switchToFreshClient();
        $this->signInThroughApi($user)->assertOk();

        $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $this->payload())->assertOk();

        $this->assertSame(1, $this->sessionCountFor($otherUser));
    }

    public function test_exceeding_the_password_change_limit_returns_too_many_requests(): void
    {
        config(['rate_limits.password_change.max_attempts_per_minute' => 2]);

        $this->signInThroughApi(User::factory()->create());

        $wrongPayload = $this->payload(currentPassword: self::WRONG_PASSWORD);

        $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $wrongPayload)->assertUnprocessable();
        $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $wrongPayload)->assertUnprocessable();

        $this->patchJson(self::CHANGE_PASSWORD_ROUTE, $wrongPayload)
            ->assertTooManyRequests()
            ->assertJsonPath('code', ErrorCode::TooManyRequests->value);
    }

    private function payload(
        string $currentPassword = UserFactory::DEFAULT_PASSWORD,
        string $password = self::NEW_PASSWORD,
        ?string $confirmation = null,
    ): array {
        return [
            'current_password' => $currentPassword,
            'password' => $password,
            'password_confirmation' => $confirmation ?? $password,
        ];
    }

    private function sessionCountFor(User $user): int
    {
        return DB::table(config('session.table'))->where('user_id', $user->id)->count();
    }
}
