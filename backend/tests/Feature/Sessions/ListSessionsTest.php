<?php

// Verifies listing the active sessions of the signed in user.
// Covers access rules, ordering, the current session flag, the exposed fields and the sessions that must never appear.

namespace Tests\Feature\Sessions;

use App\Enums\ErrorCode;
use App\Models\User;
use App\Services\Sessions\SessionIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithStoredSessions;
use Tests\TestCase;

class ListSessionsTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithStoredSessions;
    use RefreshDatabase;

    private const string SESSIONS_ROUTE = '/api/sessions';

    private const array SESSION_FIELDS = ['id', 'ip_address', 'user_agent', 'last_active_at', 'is_current'];

    private const string ISO_8601_UTC_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    private const int OLDER_SESSION_MINUTES_AGO = 5;

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson(self::SESSIONS_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    public function test_user_with_a_pending_password_change_is_forbidden(): void
    {
        $this->signInThroughApi(User::factory()->withPendingPasswordChange()->create())->assertOk();

        $this->getJson(self::SESSIONS_ROUTE)
            ->assertForbidden()
            ->assertJsonPath('code', ErrorCode::PasswordChangeRequired->value);
    }

    public function test_sessions_are_listed_newest_first_and_only_the_requesting_session_is_current(): void
    {
        $user = User::factory()->create();

        $olderSessionId = $this->signInOnFreshClient($user);
        $newerSessionId = $this->signInOnFreshClient($user);

        $this->updateSessionLastActivity(
            $olderSessionId,
            now()->subMinutes(self::OLDER_SESSION_MINUTES_AGO)->getTimestamp(),
        );

        $this->switchToClient($olderSessionId);

        $this->getJson(self::SESSIONS_ROUTE)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->sessionIdentifierFor($newerSessionId))
            ->assertJsonPath('data.0.is_current', false)
            ->assertJsonPath('data.1.id', $this->sessionIdentifierFor($olderSessionId))
            ->assertJsonPath('data.1.is_current', true);
    }

    public function test_each_session_exposes_only_the_whitelisted_fields(): void
    {
        $this->signInOnFreshClient(User::factory()->create());

        $session = $this->getJson(self::SESSIONS_ROUTE)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0');

        $this->assertEqualsCanonicalizing(self::SESSION_FIELDS, array_keys($session));
        $this->assertMatchesRegularExpression(self::ISO_8601_UTC_PATTERN, $session['last_active_at']);
        $this->assertTrue($session['is_current']);
    }

    public function test_raw_session_ids_are_never_exposed(): void
    {
        $user = User::factory()->create();

        $currentSessionId = $this->signInOnFreshClient($user);
        $storedSessionId = $this->storeSession($user);

        $response = $this->getJson(self::SESSIONS_ROUTE)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertStringNotContainsString($currentSessionId, $response->getContent());
        $this->assertStringNotContainsString($storedSessionId, $response->getContent());

        $identifiers = $response->json('data.*.id');

        $this->assertEqualsCanonicalizing(
            [$this->sessionIdentifierFor($currentSessionId), $this->sessionIdentifierFor($storedSessionId)],
            $identifiers,
        );

        foreach ($identifiers as $identifier) {
            $this->assertMatchesRegularExpression('/^'.SessionIdentifier::PATTERN.'$/', $identifier);
        }
    }

    public function test_sessions_of_other_users_are_never_listed(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->storeSession($otherUser);
        $this->signInOnFreshClient($otherUser);

        $currentSessionId = $this->signInOnFreshClient($user);

        $this->getJson(self::SESSIONS_ROUTE)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->sessionIdentifierFor($currentSessionId));
    }

    public function test_expired_sessions_are_not_listed(): void
    {
        $user = User::factory()->create();

        $currentSessionId = $this->signInOnFreshClient($user);
        $this->storeSession($user, ['last_activity' => $this->expiredActivityTimestamp()]);

        $this->getJson(self::SESSIONS_ROUTE)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->sessionIdentifierFor($currentSessionId));
    }
}
