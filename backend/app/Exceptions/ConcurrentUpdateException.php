<?php

// Raised when a workflow change is no longer valid because another user changed the request first.
// Nothing is written, and the frontend reloads the request to show its latest state.

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class ConcurrentUpdateException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::ConcurrentUpdate;
    }
}
