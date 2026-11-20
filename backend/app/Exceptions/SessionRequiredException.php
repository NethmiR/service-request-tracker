<?php

// Raised when a request that needs a session arrives without one.
// This happens when a client outside the stateful frontend origins calls sign in.

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class SessionRequiredException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::BadRequest;
    }
}
