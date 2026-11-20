<?php

// Describes one active session of a user as read from the sessions table.
// The raw session id stays inside the backend and only the identifier is ever serialized.

namespace App\Data\Sessions;

use Carbon\CarbonImmutable;

final readonly class ActiveSession
{
    public function __construct(
        public string $sessionId,
        public string $identifier,
        public int $userId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public CarbonImmutable $lastActiveAt,
        public bool $isCurrent,
    ) {}
}
