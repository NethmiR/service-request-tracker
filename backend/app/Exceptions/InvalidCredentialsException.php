<?php

// Raised when an email and password pair does not match any account.
// The same exception is used for unknown emails and wrong passwords.

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class InvalidCredentialsException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::InvalidCredentials;
    }
}
