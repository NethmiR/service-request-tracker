<?php

// Changes the password of the signed in user and ends all of their other sessions.
// The current session id is regenerated and the pending password change flag is cleared.

namespace App\Services\Auth;

use App\Data\Audit\AuthAuditEntry;
use App\Data\Auth\PasswordChange;
use App\Data\Auth\RequestContext;
use App\Enums\AuthAuditEvent;
use App\Models\User;
use App\Services\Audit\AuthAuditLogger;
use App\Services\Sessions\SessionTerminator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;

final class PasswordChangeService
{
    public function __construct(
        private readonly SessionTerminator $sessionTerminator,
        private readonly AuthAuditLogger $auditLogger,
    ) {}

    public function change(User $user, PasswordChange $change, Session $session, RequestContext $context): User
    {
        $session->regenerate(true);

        DB::transaction(function () use ($user, $change, $session, $context): void {
            $user->password = $change->newPassword;
            $user->markPasswordChanged();
            $user->save();

            $this->sessionTerminator->terminateOtherSessions($user, $session->getId());

            $this->auditLogger->record(new AuthAuditEntry(
                AuthAuditEvent::PasswordChanged,
                $user,
                null,
                null,
                $context,
            ));
        });

        return $user->refresh();
    }
}
