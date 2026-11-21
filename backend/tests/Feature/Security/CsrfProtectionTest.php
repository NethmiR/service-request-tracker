<?php

// Proves that state changing requests from the frontend are rejected without a valid CSRF token.
// The test only middleware keeps CSRF validation on, because Laravel skips it while unit tests run.

namespace Tests\Feature\Security;

use App\Enums\ErrorCode;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Support\EnforcedValidateCsrfToken;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string XSRF_COOKIE = 'XSRF-TOKEN';

    private const string XSRF_HEADER = 'X-XSRF-TOKEN';

    private const string CURRENT_USER_ROUTE = '/api/auth/user';

    private const string CHANGE_PASSWORD_ROUTE = '/api/auth/password';

    private const string NEW_PASSWORD = 'Brand-New-Password-2026!';

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.middleware.validate_csrf_token' => EnforcedValidateCsrfToken::class]);
    }

    public function test_sign_in_without_a_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->startGuestSession();

        $this->assertCsrfRejected($this->postJson(self::SIGN_IN_ROUTE, $this->credentials($user)));
    }

    public function test_sign_in_with_the_token_from_the_csrf_cookie_is_accepted(): void
    {
        $user = User::factory()->create();

        $token = $this->startSessionAndReadToken();

        $this->postJson(self::SIGN_IN_ROUTE, $this->credentials($user), [self::XSRF_HEADER => $token])->assertOk();
    }

    public function test_token_from_a_different_session_is_rejected(): void
    {
        $user = User::factory()->create();

        $otherSessionToken = $this->startSessionAndReadToken();

        $this->switchToFreshClient();
        $this->startGuestSession();

        $this->assertCsrfRejected(
            $this->postJson(self::SIGN_IN_ROUTE, $this->credentials($user), [self::XSRF_HEADER => $otherSessionToken]),
        );
    }

    public function test_reading_requests_are_not_checked_for_a_token(): void
    {
        $this->startGuestSession();

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    public function test_protected_state_changing_route_without_a_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = $this->startSessionAndReadToken();

        $this->rememberSessionCookie(
            $this->postJson(self::SIGN_IN_ROUTE, $this->credentials($user), [self::XSRF_HEADER => $token])->assertOk(),
        );

        $this->assertCsrfRejected($this->patchJson(self::CHANGE_PASSWORD_ROUTE, [
            'current_password' => UserFactory::DEFAULT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]));
    }

    private function startSessionAndReadToken(): string
    {
        return $this->startGuestSession()->getCookie(self::XSRF_COOKIE, false)->getValue();
    }

    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => UserFactory::DEFAULT_PASSWORD];
    }

    private function assertCsrfRejected(TestResponse $response): void
    {
        $response
            ->assertStatus(ErrorCode::HTTP_CSRF_TOKEN_MISMATCH)
            ->assertExactJson([
                'message' => ErrorCode::CsrfTokenMismatch->message(),
                'code' => ErrorCode::CsrfTokenMismatch->value,
            ]);
    }
}
