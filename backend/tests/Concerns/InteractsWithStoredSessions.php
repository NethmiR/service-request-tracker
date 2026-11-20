<?php

// Reads and writes rows of the sessions table directly for session management tests.
// Inserted rows stand in for sessions of other users and for expired sessions.

namespace Tests\Concerns;

use App\Models\User;
use App\Services\Sessions\SessionIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait InteractsWithStoredSessions
{
    protected const string STORED_SESSION_IP_ADDRESS = '198.51.100.7';

    protected const string STORED_SESSION_USER_AGENT = 'Stored session test agent';

    private const int STORED_SESSION_ID_LENGTH = 40;

    protected function storeSession(User $user, array $attributes = []): string
    {
        $row = array_merge([
            'id' => Str::random(self::STORED_SESSION_ID_LENGTH),
            'user_id' => $user->id,
            'ip_address' => self::STORED_SESSION_IP_ADDRESS,
            'user_agent' => self::STORED_SESSION_USER_AGENT,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ], $attributes);

        DB::table(config('session.table'))->insert($row);

        return $row['id'];
    }

    protected function updateSessionLastActivity(string $sessionId, int $lastActivity): void
    {
        DB::table(config('session.table'))
            ->where('id', $sessionId)
            ->update(['last_activity' => $lastActivity]);
    }

    protected function expiredActivityTimestamp(): int
    {
        return now()->subMinutes((int) config('session.lifetime') + 1)->getTimestamp();
    }

    protected function sessionCountFor(User $user): int
    {
        return DB::table(config('session.table'))->where('user_id', $user->id)->count();
    }

    protected function sessionIdentifierFor(string $sessionId): string
    {
        return app(SessionIdentifier::class)->forSessionId($sessionId);
    }

    protected function currentSessionIdFor(User $user): string
    {
        return (string) DB::table(config('session.table'))
            ->where('user_id', $user->id)
            ->sole()
            ->id;
    }
}
