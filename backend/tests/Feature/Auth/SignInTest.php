<?php

// Verifies the sign in endpoint, including session regeneration, enumeration protection and audit records.
// Covers valid and invalid credentials, input validation, missing sessions and pending password changes.

namespace Tests\Feature\Auth;

use App\Enums\AuthAuditEvent;
use App\Enums\ErrorCode;
use App\Models\AuthAuditLog;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use stdClass;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string TESTING_IP_ADDRESS = '127.0.0.1';

    private const string UNKNOWN_EMAIL = 'nobody@example.com';

    public function test_valid_credentials_return_the_current_user_and_authenticate(): void
    {
        $user = User::factory()->technician()->create();

        $this->startGuestSession();

        $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'password_change_required' => false,
                ],
            ]);
    }

    public function test_sign_in_issues_a_new_session_owned_by_the_user(): void
    {
        $user = User::factory()->create();

        $guestResponse = $this->startGuestSession();
        $guestCookie = $guestResponse->getCookie(config('session.cookie'), false)->getValue();

        $signInResponse = $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ])->assertOk();

        $signedInCookie = $signInResponse->getCookie(config('session.cookie'), false)->getValue();
        $guestSessionId = $guestResponse->getCookie(config('session.cookie'))->getValue();
        $signedInSessionId = $signInResponse->getCookie(config('session.cookie'))->getValue();

        $this->assertNotSame($guestCookie, $signedInCookie);
        $this->assertNotSame($guestSessionId, $signedInSessionId);
        $this->assertDatabaseMissing(config('session.table'), ['id' => $guestSessionId]);
        $this->assertDatabaseHas(config('session.table'), [
            'id' => $signedInSessionId,
            'user_id' => $user->id,
        ]);
    }

    public function test_email_is_matched_ignoring_case_and_surrounding_spaces(): void
    {
        $user = User::factory()->create(['email' => 'mixed.case@example.com']);

        $this->startGuestSession();

        $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => '  Mixed.Case@Example.com  ',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_wrong_password_and_unknown_email_return_identical_errors(): void
    {
        $user = User::factory()->create();

        $this->startGuestSession();

        $wrongPassword = $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => $user->email,
            'password' => 'Wrong-Password-2026!',
        ]);
        $unknownEmail = $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => self::UNKNOWN_EMAIL,
            'password' => 'Wrong-Password-2026!',
        ]);

        $wrongPassword->assertUnauthorized()->assertExactJson([
            'message' => ErrorCode::InvalidCredentials->message(),
            'code' => ErrorCode::InvalidCredentials->value,
        ]);
        $this->assertSame($wrongPassword->getContent(), $unknownEmail->getContent());
        $this->assertSame($wrongPassword->getStatusCode(), $unknownEmail->getStatusCode());
    }

    public function test_password_is_still_verified_for_an_unknown_email(): void
    {
        $counter = $this->countPasswordChecks();

        $this->startGuestSession();

        $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => self::UNKNOWN_EMAIL,
            'password' => 'Wrong-Password-2026!',
        ])->assertUnauthorized();

        $this->assertSame(1, $counter->checks);
    }

    public function test_missing_or_malformed_fields_return_validation_errors(): void
    {
        $this->startGuestSession();

        $this->postJson(self::SIGN_IN_ROUTE, [])
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors(['email', 'password']);

        $this->postJson(self::SIGN_IN_ROUTE, ['email' => 'not-an-email', 'password' => ['array']])
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_successful_sign_in_is_audited(): void
    {
        $user = User::factory()->create();

        $this->signInThroughApi($user)->assertOk();

        $log = AuthAuditLog::query()->sole();

        $this->assertSame(AuthAuditEvent::SignInSucceeded, $log->event);
        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertNull($log->target_user_id);
        $this->assertNull($log->attempted_email);
        $this->assertSame(self::TESTING_IP_ADDRESS, $log->ip_address);
    }

    public function test_failed_sign_in_for_an_existing_email_is_audited_with_the_target(): void
    {
        $user = User::factory()->create();

        $this->startGuestSession();
        $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => $user->email,
            'password' => 'Wrong-Password-2026!',
        ])->assertUnauthorized();

        $log = AuthAuditLog::query()->sole();

        $this->assertSame(AuthAuditEvent::SignInFailed, $log->event);
        $this->assertNull($log->actor_user_id);
        $this->assertSame($user->id, $log->target_user_id);
        $this->assertSame($user->email, $log->attempted_email);
        $this->assertSame(self::TESTING_IP_ADDRESS, $log->ip_address);
    }

    public function test_failed_sign_in_for_an_unknown_email_is_audited_without_a_target(): void
    {
        $this->startGuestSession();
        $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => self::UNKNOWN_EMAIL,
            'password' => 'Wrong-Password-2026!',
        ])->assertUnauthorized();

        $log = AuthAuditLog::query()->sole();

        $this->assertSame(AuthAuditEvent::SignInFailed, $log->event);
        $this->assertNull($log->actor_user_id);
        $this->assertNull($log->target_user_id);
        $this->assertSame(self::UNKNOWN_EMAIL, $log->attempted_email);
    }

    public function test_sign_in_without_a_stateful_origin_returns_bad_request(): void
    {
        $user = User::factory()->create();

        $this->flushHeaders();

        $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ])
            ->assertBadRequest()
            ->assertJsonPath('code', ErrorCode::BadRequest->value);

        $this->assertSame(0, DB::table(config('session.table'))->count());
    }

    public function test_user_with_a_pending_password_change_can_sign_in(): void
    {
        $user = User::factory()->withPendingPasswordChange()->create();

        $this->signInThroughApi($user)
            ->assertOk()
            ->assertJsonPath('data.password_change_required', true);
    }

    private function countPasswordChecks(): stdClass
    {
        $counter = new stdClass;
        $counter->checks = 0;

        $this->app->extend('hash', fn (HashManager $manager, Application $application): HashManager => new class($application, $counter) extends HashManager
        {
            public function __construct($container, private readonly stdClass $counter)
            {
                parent::__construct($container);
            }

            public function check($value, $hashedValue, array $options = [])
            {
                $this->counter->checks++;

                return parent::check($value, $hashedValue, $options);
            }
        });
        Hash::clearResolvedInstance('hash');

        return $counter;
    }
}
