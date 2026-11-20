<?php

// Verifies an admin listing and ending the sessions of any user, including their own.
// Covers the current session flag, the audit event chosen by who ended the session, expired rows and sessions of other users.

namespace Tests\Feature\Users;

use App\Enums\AuthAuditEvent;
use App\Enums\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuditLogs;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithStoredSessions;
use Tests\TestCase;

class UserSessionsTest extends TestCase
{
    use InteractsWithAuditLogs;
    use InteractsWithFrontendSession;
    use InteractsWithStoredSessions;
    use RefreshDatabase;

    private const string USERS_ROUTE = '/api/users';

    private const string SESSIONS_PATH = '/sessions';

    private const string CURRENT_USER_ROUTE = '/api/auth/user';

    private const string REQUEST_IP_ADDRESS = '127.0.0.1';

    private const array SESSION_FIELDS = ['id', 'ip_address', 'user_agent', 'last_active_at', 'is_current'];

    private const int IDENTIFIER_LENGTH = 64;

    public static function malformedIdentifiers(): array
    {
        return [
            'too short' => [str_repeat('a', self::IDENTIFIER_LENGTH - 1)],
            'too long' => [str_repeat('a', self::IDENTIFIER_LENGTH + 1)],
            'uppercase letters' => [str_repeat('A', self::IDENTIFIER_LENGTH)],
            'non hexadecimal characters' => [str_repeat('g', self::IDENTIFIER_LENGTH)],
        ];
    }

    public function test_admin_sees_only_the_active_sessions_of_the_user_and_none_is_current(): void
    {
        $target = User::factory()->technician()->create();
        $otherUser = User::factory()->create();

        $signedInSessionId = $this->signInOnFreshClient($target);
        $storedSessionId = $this->storeSession($target);
        $this->storeSession($target, ['last_activity' => $this->expiredActivityTimestamp()]);
        $this->storeSession($otherUser);

        $this->signInOnFreshClient(User::factory()->admin()->create());

        $sessions = $this->getJson($this->sessionsRoute($target))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        $this->assertEqualsCanonicalizing(
            [$this->sessionIdentifierFor($signedInSessionId), $this->sessionIdentifierFor($storedSessionId)],
            array_column($sessions, 'id'),
        );

        foreach ($sessions as $session) {
            $this->assertEqualsCanonicalizing(self::SESSION_FIELDS, array_keys($session));
            $this->assertFalse($session['is_current']);
        }
    }

    public function test_admin_listing_their_own_sessions_sees_the_current_one(): void
    {
        $admin = User::factory()->admin()->create();

        $otherSessionId = $this->signInOnFreshClient($admin);
        $currentSessionId = $this->signInOnFreshClient($admin);

        $sessions = collect($this->getJson($this->sessionsRoute($admin))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data'))
            ->keyBy('id');

        $this->assertTrue($sessions[$this->sessionIdentifierFor($currentSessionId)]['is_current']);
        $this->assertFalse($sessions[$this->sessionIdentifierFor($otherSessionId)]['is_current']);
    }

    public function test_ending_one_session_of_another_user_is_audited_and_signs_that_client_out(): void
    {
        $target = User::factory()->technician()->create();

        $endedSessionId = $this->signInOnFreshClient($target);
        $keptSessionId = $this->storeSession($target);

        $admin = User::factory()->admin()->create();
        $this->signInOnFreshClient($admin);

        $this->deleteJson($this->sessionRoute($target, $this->sessionIdentifierFor($endedSessionId)))
            ->assertNoContent();

        $this->assertDatabaseMissing(config('session.table'), ['id' => $endedSessionId]);
        $this->assertDatabaseHas(config('session.table'), ['id' => $keptSessionId, 'user_id' => $target->id]);

        $log = $this->auditLogsFor(AuthAuditEvent::SessionClearedByAdmin)->sole();

        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame($target->id, $log->target_user_id);
        $this->assertSame(self::REQUEST_IP_ADDRESS, $log->ip_address);
        $this->assertCount(0, $this->sessionClearedByOwnerLogs());

        $this->switchToClient($endedSessionId);

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    public function test_session_of_a_different_user_than_the_one_in_the_url_is_not_found(): void
    {
        $target = User::factory()->create();
        $otherSessionId = $this->storeSession(User::factory()->create());

        $this->signInOnFreshClient(User::factory()->admin()->create());

        $this->deleteJson($this->sessionRoute($target, $this->sessionIdentifierFor($otherSessionId)))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);

        $this->assertDatabaseHas(config('session.table'), ['id' => $otherSessionId]);
        $this->assertNoSessionAuditLogs();
    }

    #[DataProvider('malformedIdentifiers')]
    public function test_malformed_identifier_is_not_found(string $identifier): void
    {
        $target = User::factory()->create();

        $this->signInOnFreshClient(User::factory()->admin()->create());

        $this->deleteJson($this->sessionRoute($target, $identifier))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);
    }

    public function test_ending_the_admins_own_current_session_is_a_conflict(): void
    {
        $admin = User::factory()->admin()->create();

        $currentSessionId = $this->signInOnFreshClient($admin);

        $this->deleteJson($this->sessionRoute($admin, $this->sessionIdentifierFor($currentSessionId)))
            ->assertConflict()
            ->assertJsonPath('code', ErrorCode::CurrentSessionNotClearable->value);

        $this->assertDatabaseHas(config('session.table'), ['id' => $currentSessionId, 'user_id' => $admin->id]);
        $this->assertNoSessionAuditLogs();
    }

    public function test_ending_another_own_session_through_the_admin_route_is_audited_as_cleared_by_owner(): void
    {
        $admin = User::factory()->admin()->create();

        $otherSessionId = $this->signInOnFreshClient($admin);
        $currentSessionId = $this->signInOnFreshClient($admin);

        $this->deleteJson($this->sessionRoute($admin, $this->sessionIdentifierFor($otherSessionId)))
            ->assertNoContent();

        $this->assertDatabaseMissing(config('session.table'), ['id' => $otherSessionId]);
        $this->assertDatabaseHas(config('session.table'), ['id' => $currentSessionId, 'user_id' => $admin->id]);

        $log = $this->sessionClearedByOwnerLogs()->sole();

        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame($admin->id, $log->target_user_id);
        $this->assertCount(0, $this->auditLogsFor(AuthAuditEvent::SessionClearedByAdmin));
    }

    public function test_ending_all_sessions_of_another_user_deletes_every_row_and_audits_each_active_one(): void
    {
        $target = User::factory()->technician()->create();
        $otherUser = User::factory()->create();

        $firstSessionId = $this->signInOnFreshClient($target);
        $secondSessionId = $this->signInOnFreshClient($target);
        $this->storeSession($target, ['last_activity' => $this->expiredActivityTimestamp()]);
        $otherSessionId = $this->storeSession($otherUser);

        $admin = User::factory()->admin()->create();
        $adminSessionId = $this->signInOnFreshClient($admin);

        $this->deleteJson($this->sessionsRoute($target))->assertNoContent();

        $this->assertSame(0, $this->sessionCountFor($target));
        $this->assertDatabaseHas(config('session.table'), ['id' => $otherSessionId, 'user_id' => $otherUser->id]);
        $this->assertDatabaseHas(config('session.table'), ['id' => $adminSessionId, 'user_id' => $admin->id]);

        $logs = $this->auditLogsFor(AuthAuditEvent::SessionClearedByAdmin);

        $this->assertCount(2, $logs);

        foreach ($logs as $log) {
            $this->assertSame($admin->id, $log->actor_user_id);
            $this->assertSame($target->id, $log->target_user_id);
        }

        $this->assertCount(0, $this->sessionClearedByOwnerLogs());

        foreach ([$firstSessionId, $secondSessionId] as $endedSessionId) {
            $this->switchToClient($endedSessionId);

            $this->getJson(self::CURRENT_USER_ROUTE)
                ->assertUnauthorized()
                ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
        }
    }

    public function test_ending_all_own_sessions_through_the_admin_route_keeps_the_current_session(): void
    {
        $admin = User::factory()->admin()->create();

        $otherSessionId = $this->signInOnFreshClient($admin);
        $currentSessionId = $this->signInOnFreshClient($admin);

        $this->deleteJson($this->sessionsRoute($admin))->assertNoContent();

        $this->assertSame(1, $this->sessionCountFor($admin));
        $this->assertDatabaseMissing(config('session.table'), ['id' => $otherSessionId]);
        $this->assertDatabaseHas(config('session.table'), ['id' => $currentSessionId, 'user_id' => $admin->id]);
        $this->assertCount(1, $this->sessionClearedByOwnerLogs());
        $this->assertCount(0, $this->auditLogsFor(AuthAuditEvent::SessionClearedByAdmin));

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id);
    }

    public function test_ending_all_sessions_of_a_user_without_sessions_writes_no_audit_record(): void
    {
        $target = User::factory()->create();

        $this->signInOnFreshClient(User::factory()->admin()->create());

        $this->deleteJson($this->sessionsRoute($target))->assertNoContent();

        $this->assertNoSessionAuditLogs();
    }

    private function sessionsRoute(User $owner): string
    {
        return self::USERS_ROUTE.'/'.$owner->id.self::SESSIONS_PATH;
    }

    private function sessionRoute(User $owner, string $identifier): string
    {
        return $this->sessionsRoute($owner).'/'.$identifier;
    }

    private function assertNoSessionAuditLogs(): void
    {
        $this->assertCount(0, $this->auditLogsFor(AuthAuditEvent::SessionClearedByAdmin));
        $this->assertCount(0, $this->sessionClearedByOwnerLogs());
    }
}
