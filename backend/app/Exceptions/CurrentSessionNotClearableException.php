<?php

// Raised when a user tries to end the session making the request through session management.
// The current session is ended only by signing out.

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class CurrentSessionNotClearableException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::CurrentSessionNotClearable;
    }
}
