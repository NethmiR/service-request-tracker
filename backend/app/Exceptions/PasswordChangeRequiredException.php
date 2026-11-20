<?php

// Raised when a user with a pending password change calls a protected endpoint.
// The frontend uses its error code to route the user to the change password page.

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class PasswordChangeRequiredException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::PasswordChangeRequired;
    }
}
