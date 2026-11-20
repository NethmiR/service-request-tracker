<?php

// Lists the authentication events recorded in the audit table.
// These values never leave the backend.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum AuthAuditEvent: string
{
    use ProvidesValues;

    case SignInSucceeded = 'SIGN_IN_SUCCEEDED';
    case SignInFailed = 'SIGN_IN_FAILED';
    case SignInThrottled = 'SIGN_IN_THROTTLED';
    case SignedOut = 'SIGNED_OUT';
    case PasswordChanged = 'PASSWORD_CHANGED';
    case PasswordResetByAdmin = 'PASSWORD_RESET_BY_ADMIN';
    case UserCreatedByAdmin = 'USER_CREATED_BY_ADMIN';
    case SessionClearedByOwner = 'SESSION_CLEARED_BY_OWNER';
    case SessionClearedByAdmin = 'SESSION_CLEARED_BY_ADMIN';
}
