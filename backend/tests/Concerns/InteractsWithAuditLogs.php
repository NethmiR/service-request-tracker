<?php

// Reads rows of the authentication audit table back in feature tests.
// Rows are filtered by event so each test can count and inspect only the records it expects.

namespace Tests\Concerns;

use App\Enums\AuthAuditEvent;
use App\Models\AuthAuditLog;
use Illuminate\Support\Collection;

trait InteractsWithAuditLogs
{
    protected function auditLogsFor(AuthAuditEvent $event): Collection
    {
        return AuthAuditLog::query()
            ->where('event', $event)
            ->orderBy('id')
            ->get();
    }

    protected function sessionClearedByOwnerLogs(): Collection
    {
        return $this->auditLogsFor(AuthAuditEvent::SessionClearedByOwner);
    }
}
