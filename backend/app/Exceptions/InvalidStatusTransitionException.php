<?php

// Raised when a status change targets a status the transition rules do not allow from the current one.
// OPEN is never an allowed target, because a request leaves it only through self assignment.

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class InvalidStatusTransitionException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::InvalidStatusTransition;
    }
}
