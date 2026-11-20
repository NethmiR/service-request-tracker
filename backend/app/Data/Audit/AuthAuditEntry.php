<?php

// Describes one authentication event to be written to the audit table.
// Built by the authentication services and consumed by the audit logger.

namespace App\Data\Audit;

use App\Data\Auth\RequestContext;
use App\Enums\AuthAuditEvent;
use App\Models\User;

final readonly class AuthAuditEntry
{
    public function __construct(
        public AuthAuditEvent $event,
        public ?User $actor,
        public ?User $targetUser,
        public ?string $attemptedEmail,
        public RequestContext $context,
    ) {}
}
