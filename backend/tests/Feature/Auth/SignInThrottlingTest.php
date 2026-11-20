<?php

// Verifies that failed sign in attempts are limited per email and IP and per IP.
// Covers the retry header, the audit record, clearing after success and the decay time.

namespace Tests\Feature\Auth;

use App\Enums\AuthAuditEvent;
use App\Enums\ErrorCode;
use App\Models\AuthAuditLog;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class SignInThrottlingTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const int MAX_FAILURES_PER_EMAIL_AND_IP = 3;

    private const int MAX_FAILURES_PER_IP = 5;

    private const int DECAY_SECONDS = 60;

    private const string WRONG_PASSWORD = 'Wrong-Password-2026!';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rate_limits.sign_in.max_failures_per_email_and_ip' => self::MAX_FAILURES_PER_EMAIL_AND_IP,
            'rate_limits.sign_in.max_failures_per_ip' => self::MAX_FAILURES_PER_IP,
            'rate_limits.sign_in.decay_seconds' => self::DECAY_SECONDS,
        ]);

        $this->startGuestSession();
    }

    public function test_attempt_after_the_limit_is_throttled_even_with_the_correct_password(): void
    {
        $user = User::factory()->create();

        $this->failSignIn($user->email, self::MAX_FAILURES_PER_EMAIL_AND_IP);

        $this->signIn($user->email, UserFactory::DEFAULT_PASSWORD)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('code', ErrorCode::TooManyRequests->value);

        $log = AuthAuditLog::query()->where('event', AuthAuditEvent::SignInThrottled)->sole();

        $this->assertSame($user->email, $log->attempted_email);
        $this->assertNull($log->actor_user_id);
    }

    public function test_failures_across_different_emails_hit_the_per_ip_limit(): void
    {
        for ($attempt = 1; $attempt <= self::MAX_FAILURES_PER_IP; $attempt++) {
            $this->signIn("person{$attempt}@example.com", self::WRONG_PASSWORD)->assertUnauthorized();
        }

        $this->signIn('another.person@example.com', self::WRONG_PASSWORD)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_successful_sign_in_clears_the_email_and_ip_counter(): void
    {
        $user = User::factory()->create();

        $this->failSignIn($user->email, self::MAX_FAILURES_PER_EMAIL_AND_IP - 1);
        $this->signIn($user->email, UserFactory::DEFAULT_PASSWORD)->assertOk();

        $this->switchToFreshClient();
        $this->startGuestSession();

        $this->failSignIn($user->email, self::MAX_FAILURES_PER_EMAIL_AND_IP - 1);
        $this->signIn($user->email, self::WRONG_PASSWORD)->assertUnauthorized();
    }

    public function test_counters_reset_after_the_decay_time(): void
    {
        $user = User::factory()->create();

        $this->failSignIn($user->email, self::MAX_FAILURES_PER_EMAIL_AND_IP);
        $this->signIn($user->email, UserFactory::DEFAULT_PASSWORD)->assertTooManyRequests();

        $this->travel(self::DECAY_SECONDS + 1)->seconds();

        $this->signIn($user->email, UserFactory::DEFAULT_PASSWORD)->assertOk();
    }

    private function failSignIn(string $email, int $times): void
    {
        for ($attempt = 0; $attempt < $times; $attempt++) {
            $this->signIn($email, self::WRONG_PASSWORD)->assertUnauthorized();
        }
    }

    private function signIn(string $email, string $password): TestResponse
    {
        return $this->postJson(self::SIGN_IN_ROUTE, ['email' => $email, 'password' => $password]);
    }
}
