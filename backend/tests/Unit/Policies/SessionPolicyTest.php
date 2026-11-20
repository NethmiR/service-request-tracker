<?php

// Verifies who may end a stored session through the session policy.
// The owner and admins may, while any other user may not.

namespace Tests\Unit\Policies;

use App\Data\Sessions\ActiveSession;
use App\Models\User;
use App\Policies\SessionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SessionPolicyTest extends TestCase
{
    private const int OWNER_ID = 1;

    private const int OTHER_USER_ID = 2;

    private const string RAW_SESSION_ID = 'RawSessionIdForSessionPolicyTests1234567';

    public function test_policy_is_registered_for_active_sessions(): void
    {
        $this->assertInstanceOf(SessionPolicy::class, Gate::getPolicyFor(ActiveSession::class));
    }

    public function test_owner_may_delete_their_session(): void
    {
        $owner = User::factory()->requester()->make(['id' => self::OWNER_ID]);

        $this->assertTrue((new SessionPolicy)->delete($owner, $this->sessionOwnedBy(self::OWNER_ID)));
    }

    public function test_other_user_may_not_delete_the_session(): void
    {
        $otherUser = User::factory()->technician()->make(['id' => self::OTHER_USER_ID]);

        $this->assertFalse((new SessionPolicy)->delete($otherUser, $this->sessionOwnedBy(self::OWNER_ID)));
    }

    public function test_admin_may_delete_another_users_session(): void
    {
        $admin = User::factory()->admin()->make(['id' => self::OTHER_USER_ID]);

        $this->assertTrue((new SessionPolicy)->delete($admin, $this->sessionOwnedBy(self::OWNER_ID)));
    }

    private function sessionOwnedBy(int $userId): ActiveSession
    {
        return new ActiveSession(
            self::RAW_SESSION_ID,
            str_repeat('a', 64),
            $userId,
            null,
            null,
            CarbonImmutable::now(),
            false,
        );
    }
}
