<?php

// Raised when a session identifier matches none of the user's active sessions.
// The same response covers sessions of other users, expired sessions and sessions already ended.

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class SessionNotFoundException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::NotFound;
    }
}
