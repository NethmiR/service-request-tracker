<?php

// Writes authentication events to the append only audit table.
// It only creates rows and never updates or deletes them.

namespace App\Services\Audit;

use App\Data\Audit\AuthAuditEntry;
use App\Models\AuthAuditLog;

final class AuthAuditLogger
{
    public function record(AuthAuditEntry $entry): void
    {
        AuthAuditLog::query()->create([
            'event' => $entry->event,
            'actor_user_id' => $entry->actor?->getKey(),
            'target_user_id' => $entry->targetUser?->getKey(),
            'attempted_email' => $entry->attemptedEmail,
            'ip_address' => $entry->context->ipAddress,
            'user_agent' => $entry->context->userAgent,
        ]);
    }
}
