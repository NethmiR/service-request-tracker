<?php

// Verifies ending every session of the signed in user except the current one.
// Covers the audit records, signing out the ended clients, expired rows and the sessions of other users.

namespace Tests\Feature\Sessions;

use App\Enums\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithStoredSessions;
use Tests\TestCase;

class ClearOtherSessionsTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithStoredSessions;
    use RefreshDatabase;

    private const string OTHER_SESSIONS_ROUTE = '/api/sessions/others';

    private const string CURRENT_USER_ROUTE = '/api/auth/user';

    public function test_every_other_session_is_ended_and_audited(): void
    {
        $user = User::factory()->create();

        $firstSessionId = $this->signInOnFreshClient($user);
        $secondSessionId = $this->signInOnFreshClient($user);
        $currentSessionId = $this->signInOnFreshClient($user);

        $this->deleteJson(self::OTHER_SESSIONS_ROUTE)->assertNoContent();

        $this->assertSame(1, $this->sessionCountFor($user));
        $this->assertDatabaseHas(config('session.table'), ['id' => $currentSessionId, 'user_id' => $user->id]);

        $logs = $this->sessionClearedByOwnerLogs();

        $this->assertCount(2, $logs);

        foreach ($logs as $log) {
            $this->assertSame($user->id, $log->actor_user_id);
            $this->assertSame($user->id, $log->target_user_id);
        }

        foreach ([$firstSessionId, $secondSessionId] as $endedSessionId) {
            $this->switchToClient($endedSessionId);

            $this->getJson(self::CURRENT_USER_ROUTE)
                ->assertUnauthorized()
                ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
        }
    }

    public function test_expired_sessions_are_deleted_without_being_audited(): void
    {
        $user = User::factory()->create();

        $currentSessionId = $this->signInOnFreshClient($user);
        $expiredSessionId = $this->storeSession($user, ['last_activity' => $this->expiredActivityTimestamp()]);

        $this->deleteJson(self::OTHER_SESSIONS_ROUTE)->assertNoContent();

        $this->assertDatabaseMissing(config('session.table'), ['id' => $expiredSessionId]);
        $this->assertDatabaseHas(config('session.table'), ['id' => $currentSessionId]);
        $this->assertCount(0, $this->sessionClearedByOwnerLogs());
    }

    public function test_sessions_of_other_users_are_not_touched(): void
    {
        $otherUser = User::factory()->create();
        $otherSessionId = $this->storeSession($otherUser);

        $this->signInOnFreshClient(User::factory()->create());

        $this->deleteJson(self::OTHER_SESSIONS_ROUTE)->assertNoContent();

        $this->assertDatabaseHas(config('session.table'), ['id' => $otherSessionId]);
        $this->assertSame(1, $this->sessionCountFor($otherUser));
    }

    public function test_no_other_sessions_writes_no_audit_record(): void
    {
        $user = User::factory()->create();

        $this->signInOnFreshClient($user);

        $this->deleteJson(self::OTHER_SESSIONS_ROUTE)->assertNoContent();

        $this->assertSame(1, $this->sessionCountFor($user));
        $this->assertCount(0, $this->sessionClearedByOwnerLogs());
    }
}
