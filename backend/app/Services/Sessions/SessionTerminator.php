<?php

// Deletes stored sessions of a user from the sessions table.
// Used by password changes, self service session management and admin password resets.

namespace App\Services\Sessions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SessionTerminator
{
    public function terminateSession(User $user, string $sessionId): int
    {
        return DB::table($this->table())
            ->where('user_id', $user->getKey())
            ->where('id', $sessionId)
            ->delete();
    }

    public function terminateOtherSessions(User $user, string $currentSessionId): int
    {
        return DB::table($this->table())
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    public function terminateAllSessions(User $user): int
    {
        return DB::table($this->table())
            ->where('user_id', $user->getKey())
            ->delete();
    }

    private function table(): string
    {
        return (string) config('session.table');
    }
}
