<?php

// Lists the statuses a service request moves through and holds the transition rules between them.
// The frontend mirrors these values in enums/service-request-status.ts and never repeats the rules.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum ServiceRequestStatus: string
{
    use ProvidesValues;

    case Open = 'OPEN';
    case Assigned = 'ASSIGNED';
    case InProgress = 'IN_PROGRESS';
    case Resolved = 'RESOLVED';

    public function isAssignable(): bool
    {
        return $this === self::Open;
    }

    public function allowedTargets(): array
    {
        return match ($this) {
            self::Open => [],
            self::Assigned => [self::InProgress, self::Resolved],
            self::InProgress => [self::Assigned, self::Resolved],
            self::Resolved => [self::Assigned, self::InProgress],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
