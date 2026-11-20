<?php

// Reads the active sessions of a user from the sessions table without touching the payload.
// A session is active while its last activity falls within the configured session lifetime.

namespace App\Services\Sessions;

use App\Data\Sessions\ActiveSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ActiveSessionRepository
{
    public function __construct(
        private readonly SessionIdentifier $sessionIdentifier,
    ) {}

    public function forUser(User $user, string $currentSessionId): Collection
    {
        return DB::table($this->table())
            ->select(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity'])
            ->where('user_id', $user->getKey())
            ->where('last_activity', '>=', $this->activityThreshold())
            ->orderByDesc('last_activity')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): ActiveSession => new ActiveSession(
                (string) $row->id,
                $this->sessionIdentifier->forSessionId((string) $row->id),
                (int) $row->user_id,
                $row->ip_address === null ? null : (string) $row->ip_address,
                $row->user_agent === null ? null : (string) $row->user_agent,
                CarbonImmutable::createFromTimestampUTC((int) $row->last_activity),
                hash_equals((string) $row->id, $currentSessionId),
            ))
            ->values();
    }

    public function findForUser(User $user, string $identifier, string $currentSessionId): ?ActiveSession
    {
        return $this->forUser($user, $currentSessionId)->first(
            fn (ActiveSession $session): bool => $this->sessionIdentifier->matches($identifier, $session->sessionId),
        );
    }

    private function activityThreshold(): int
    {
        return now()->subMinutes((int) config('session.lifetime'))->getTimestamp();
    }

    private function table(): string
    {
        return (string) config('session.table');
    }
}
