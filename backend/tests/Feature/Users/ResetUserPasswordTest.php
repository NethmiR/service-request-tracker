<?php

// Verifies an admin resetting the password of a user, including their own.
// Covers the new hash, the pending flag, ended sessions, the audit record, rate limiting and the null delivery contract.

namespace Tests\Feature\Users;

use App\Contracts\Users\TemporaryPasswordDelivery;
use App\Enums\AuthAuditEvent;
use App\Enums\ErrorCode;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAuditLogs;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithStoredSessions;
use Tests\TestCase;

class ResetUserPasswordTest extends TestCase
{
    use InteractsWithAuditLogs;
    use InteractsWithFrontendSession;
    use InteractsWithStoredSessions;
    use RefreshDatabase;

    private const string USERS_ROUTE = '/api/users';

    private const string PASSWORD_RESET_PATH = '/password-reset';

    private const string CURRENT_USER_ROUTE = '/api/auth/user';

    private const string REQUEST_IP_ADDRESS = '127.0.0.1';

    public function test_resetting_another_user_returns_a_temporary_password_that_is_never_cached(): void
    {
        $target = User::factory()->technician()->create();

        $this->signInAsAdmin();

        $response = $this->resetPassword($target)
            ->assertOk()
            ->assertJsonPath('data.user.id', $target->id)
            ->assertJsonPath('data.user.password_change_required', true);

        $this->assertNotEmpty($response->json('data.temporary_password'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_target_gets_the_new_hash_and_a_pending_change_and_the_old_password_stops_working(): void
    {
        $target = User::factory()->technician()->create();

        $this->signInAsAdmin();

        $temporaryPassword = $this->resetPassword($target)->assertOk()->json('data.temporary_password');

        $target->refresh();

        $this->assertTrue(Hash::check($temporaryPassword, $target->password));
        $this->assertFalse(Hash::check(UserFactory::DEFAULT_PASSWORD, $target->password));
        $this->assertTrue($target->requiresPasswordChange());

        $this->switchToFreshClient();

        $this->signInThroughApi($target)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::InvalidCredentials->value);

        $this->switchToFreshClient();

        $this->signInThroughApi($target, $temporaryPassword)
            ->assertOk()
            ->assertJsonPath('data.password_change_required', true);
    }

    public function test_every_session_of_the_target_is_ended_and_its_clients_are_signed_out(): void
    {
        $target = User::factory()->technician()->create();

        $targetSessionId = $this->signInOnFreshClient($target);
        $this->storeSession($target);
        $this->storeSession($target, ['last_activity' => $this->expiredActivityTimestamp()]);

        $this->signInOnFreshClient(User::factory()->admin()->create());

        $this->resetPassword($target)->assertOk();

        $this->assertSame(0, $this->sessionCountFor($target));

        $this->switchToClient($targetSessionId);

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    public function test_sessions_of_other_users_and_the_admin_are_not_touched(): void
    {
        $target = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherSessionId = $this->storeSession($otherUser);

        $admin = User::factory()->admin()->create();
        $adminSessionId = $this->signInOnFreshClient($admin);

        $this->resetPassword($target)->assertOk();

        $this->assertDatabaseHas(config('session.table'), ['id' => $otherSessionId, 'user_id' => $otherUser->id]);
        $this->assertDatabaseHas(config('session.table'), ['id' => $adminSessionId, 'user_id' => $admin->id]);

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id);
    }

    public function test_reset_is_audited_with_the_admin_as_actor_and_the_user_as_target(): void
    {
        $target = User::factory()->create();

        $admin = $this->signInAsAdmin();

        $this->resetPassword($target)->assertOk();

        $log = $this->auditLogsFor(AuthAuditEvent::PasswordResetByAdmin)->sole();

        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame($target->id, $log->target_user_id);
        $this->assertSame(self::REQUEST_IP_ADDRESS, $log->ip_address);
    }

    public function test_resetting_a_user_with_a_pending_change_succeeds(): void
    {
        $target = User::factory()->withPendingPasswordChange()->create();

        $this->signInAsAdmin();

        $temporaryPassword = $this->resetPassword($target)
            ->assertOk()
            ->assertJsonPath('data.user.password_change_required', true)
            ->json('data.temporary_password');

        $this->assertTrue(Hash::check($temporaryPassword, $target->refresh()->password));
    }

    public function test_admin_resetting_their_own_password_ends_every_own_session_including_the_current_one(): void
    {
        $admin = User::factory()->admin()->create();

        $this->signInOnFreshClient($admin);
        $currentSessionId = $this->signInOnFreshClient($admin);

        $temporaryPassword = $this->resetPassword($admin)
            ->assertOk()
            ->assertJsonPath('data.user.id', $admin->id)
            ->assertJsonPath('data.user.password_change_required', true)
            ->json('data.temporary_password');

        $this->assertNotEmpty($temporaryPassword);
        $this->assertSame(0, $this->sessionCountFor($admin));
        $this->assertDatabaseMissing(config('session.table'), ['id' => $currentSessionId, 'user_id' => $admin->id]);
        $this->assertCount(1, $this->auditLogsFor(AuthAuditEvent::PasswordResetByAdmin));

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);

        $this->switchToFreshClient();

        $this->signInThroughApi($admin, $temporaryPassword)
            ->assertOk()
            ->assertJsonPath('data.password_change_required', true);
    }

    public function test_exceeding_the_admin_reset_limit_returns_too_many_requests(): void
    {
        config(['rate_limits.admin_password_reset.max_attempts_per_minute' => 2]);

        $target = User::factory()->create();

        $this->signInAsAdmin();

        $this->resetPassword($target)->assertOk();
        $this->resetPassword($target)->assertOk();

        $this->resetPassword($target)
            ->assertTooManyRequests()
            ->assertJsonPath('code', ErrorCode::TooManyRequests->value);

        $this->assertCount(2, $this->auditLogsFor(AuthAuditEvent::PasswordResetByAdmin));
    }

    public function test_delivery_that_sends_the_password_elsewhere_returns_no_temporary_password(): void
    {
        $this->app->bind(TemporaryPasswordDelivery::class, fn (): TemporaryPasswordDelivery => new class implements TemporaryPasswordDelivery
        {
            public function deliver(User $user, string $temporaryPassword): ?string
            {
                return null;
            }
        });

        $target = User::factory()->create();

        $this->signInAsAdmin();

        $this->resetPassword($target)
            ->assertOk()
            ->assertJsonPath('data.user.id', $target->id)
            ->assertJsonPath('data.temporary_password', null);

        $this->assertTrue($target->refresh()->requiresPasswordChange());
    }

    private function signInAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();

        $this->signInThroughApi($admin)->assertOk();

        return $admin;
    }

    private function resetPassword(User $target): TestResponse
    {
        return $this->postJson(self::USERS_ROUTE.'/'.$target->id.self::PASSWORD_RESET_PATH);
    }
}
