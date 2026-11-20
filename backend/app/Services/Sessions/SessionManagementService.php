<?php

// Lists and ends the active sessions of the signed in user and records each ended session in the audit table.
// The current session is never ended here, because signing out is the only way to end it.

namespace App\Services\Sessions;

use App\Data\Audit\AuthAuditEntry;
use App\Data\Auth\RequestContext;
use App\Data\Sessions\ActiveSession;
use App\Enums\AuthAuditEvent;
use App\Exceptions\CurrentSessionNotClearableException;
use App\Exceptions\SessionNotFoundException;
use App\Models\User;
use App\Services\Audit\AuthAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SessionManagementService
{
    public function __construct(
        private readonly ActiveSessionRepository $activeSessionRepository,
        private readonly SessionTerminator $sessionTerminator,
        private readonly AuthAuditLogger $auditLogger,
    ) {}

    public function listOwnSessions(User $owner, string $currentSessionId): Collection
    {
        return $this->activeSessionRepository->forUser($owner, $currentSessionId);
    }

    public function findOwnSession(User $owner, string $identifier, string $currentSessionId): ActiveSession
    {
        return $this->activeSessionRepository->findForUser($owner, $identifier, $currentSessionId)
            ?? throw new SessionNotFoundException;
    }

    public function clearOwnSession(User $owner, ActiveSession $session, RequestContext $context): void
    {
        if ($session->isCurrent) {
            throw new CurrentSessionNotClearableException;
        }

        DB::transaction(function () use ($owner, $session, $context): void {
            if ($this->sessionTerminator->terminateSession($owner, $session->sessionId) === 0) {
                throw new SessionNotFoundException;
            }

            $this->auditLogger->record($this->sessionClearedByOwnerEntry($owner, $context));
        });
    }

    public function clearOtherOwnSessions(User $owner, string $currentSessionId, RequestContext $context): int
    {
        return DB::transaction(function () use ($owner, $currentSessionId, $context): int {
            $endedSessions = $this->activeSessionRepository
                ->forUser($owner, $currentSessionId)
                ->reject(fn (ActiveSession $session): bool => $session->isCurrent);

            $this->sessionTerminator->terminateOtherSessions($owner, $currentSessionId);

            foreach ($endedSessions as $endedSession) {
                $this->auditLogger->record($this->sessionClearedByOwnerEntry($owner, $context));
            }

            return $endedSessions->count();
        });
    }

    private function sessionClearedByOwnerEntry(User $owner, RequestContext $context): AuthAuditEntry
    {
        return new AuthAuditEntry(
            AuthAuditEvent::SessionClearedByOwner,
            $owner,
            $owner,
            null,
            $context,
        );
    }
}
