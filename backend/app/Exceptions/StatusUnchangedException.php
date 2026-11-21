<?php

// Raised when a status change targets the status the request already has.
// A no op status change is rejected so it never writes a history record.

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class StatusUnchangedException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::StatusUnchanged;
    }
}
