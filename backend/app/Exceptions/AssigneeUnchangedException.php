<?php

// Raised when a request is reassigned to the user who is already its assignee.
// A no op reassignment is rejected so it never writes a history record.

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class AssigneeUnchangedException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::AssigneeUnchanged;
    }
}
