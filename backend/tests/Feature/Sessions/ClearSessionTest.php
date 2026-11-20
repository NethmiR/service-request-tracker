<?php

// Verifies ending one session of the signed in user.
// Covers the audit record, signing out the ended client and every case that must leave sessions and the audit table untouched.

namespace Tests\Feature\Sessions;

use App\Enums\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuditLogs;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithStoredSessions;
use Tests\TestCase;

class ClearSessionTest extends TestCase
{
    use InteractsWithAuditLogs;
    use InteractsWithFrontendSession;
    use InteractsWithStoredSessions;
    use RefreshDatabase;

    private const string SESSIONS_ROUTE = '/api/sessions';

    private const string CURRENT_USER_ROUTE = '/api/auth/user';

    private const string REQUEST_IP_ADDRESS = '127.0.0.1';

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

    public function test_ending_another_session_deletes_only_that_session_and_is_audited(): void
    {
        $user = User::factory()->create();

        $otherSessionId = $this->signInOnFreshClient($user);
        $currentSessionId = $this->signInOnFreshClient($user);

        $this->deleteJson($this->sessionRoute($this->sessionIdentifierFor($otherSessionId)))
            ->assertNoContent();

        $this->assertDatabaseMissing(config('session.table'), ['id' => $otherSessionId]);
        $this->assertDatabaseHas(config('session.table'), ['id' => $currentSessionId, 'user_id' => $user->id]);

        $log = $this->sessionClearedByOwnerLogs()->sole();

        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertSame($user->id, $log->target_user_id);
        $this->assertSame(self::REQUEST_IP_ADDRESS, $log->ip_address);
    }

    public function test_ended_client_is_signed_out_while_the_current_client_stays_signed_in(): void
    {
        $user = User::factory()->create();

        $otherSessionId = $this->signInOnFreshClient($user);
        $currentSessionId = $this->signInOnFreshClient($user);

        $this->deleteJson($this->sessionRoute($this->sessionIdentifierFor($otherSessionId)))
            ->assertNoContent();

        $this->switchToClient($otherSessionId);

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);

        $this->switchToClient($currentSessionId);

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_ending_the_current_session_is_a_conflict(): void
    {
        $user = User::factory()->create();

        $this->signInThroughApi($user)->assertOk();
        $currentSessionId = $this->currentSessionIdFor($user);

        $this->deleteJson($this->sessionRoute($this->sessionIdentifierFor($currentSessionId)))
            ->assertConflict()
            ->assertJsonPath('code', ErrorCode::CurrentSessionNotClearable->value);

        $this->assertDatabaseHas(config('session.table'), ['id' => $currentSessionId]);
        $this->assertCount(0, $this->sessionClearedByOwnerLogs());
    }

    public function test_session_of_another_user_is_not_found(): void
    {
        $otherSessionId = $this->storeSession(User::factory()->create());

        $this->signInOnFreshClient(User::factory()->create());

        $this->deleteJson($this->sessionRoute($this->sessionIdentifierFor($otherSessionId)))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);

        $this->assertDatabaseHas(config('session.table'), ['id' => $otherSessionId]);
        $this->assertCount(0, $this->sessionClearedByOwnerLogs());
    }

    public function test_well_formed_identifier_matching_no_session_is_not_found(): void
    {
        $this->signInOnFreshClient(User::factory()->create());

        $this->deleteJson($this->sessionRoute(str_repeat('a', self::IDENTIFIER_LENGTH)))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);
    }

    #[DataProvider('malformedIdentifiers')]
    public function test_malformed_identifier_is_not_found(string $identifier): void
    {
        $this->signInOnFreshClient(User::factory()->create());

        $this->deleteJson($this->sessionRoute($identifier))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);
    }

    public function test_expired_session_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->signInOnFreshClient($user);
        $expiredSessionId = $this->storeSession($user, ['last_activity' => $this->expiredActivityTimestamp()]);

        $this->deleteJson($this->sessionRoute($this->sessionIdentifierFor($expiredSessionId)))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);

        $this->assertDatabaseHas(config('session.table'), ['id' => $expiredSessionId]);
        $this->assertCount(0, $this->sessionClearedByOwnerLogs());
    }

    public function test_ending_the_same_session_twice_is_not_found_the_second_time(): void
    {
        $user = User::factory()->create();

        $otherSessionId = $this->signInOnFreshClient($user);
        $this->signInOnFreshClient($user);

        $route = $this->sessionRoute($this->sessionIdentifierFor($otherSessionId));

        $this->deleteJson($route)->assertNoContent();

        $this->deleteJson($route)
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);

        $this->assertCount(1, $this->sessionClearedByOwnerLogs());
    }

    public function test_user_with_a_pending_password_change_is_forbidden(): void
    {
        $user = User::factory()->withPendingPasswordChange()->create();

        $this->signInThroughApi($user)->assertOk();
        $storedSessionId = $this->storeSession($user);

        $this->deleteJson($this->sessionRoute($this->sessionIdentifierFor($storedSessionId)))
            ->assertForbidden()
            ->assertJsonPath('code', ErrorCode::PasswordChangeRequired->value);

        $this->assertDatabaseHas(config('session.table'), ['id' => $storedSessionId]);
        $this->assertCount(0, $this->sessionClearedByOwnerLogs());
    }

    private function sessionRoute(string $identifier): string
    {
        return self::SESSIONS_ROUTE.'/'.$identifier;
    }
}
