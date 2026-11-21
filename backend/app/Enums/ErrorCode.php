<?php

// Lists every error code the API returns, with its HTTP status and message.
// The frontend mirrors these values in enums/error-code.ts.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;
use Symfony\Component\HttpFoundation\Response;

enum ErrorCode: string
{
    use ProvidesValues;

    public const int HTTP_CSRF_TOKEN_MISMATCH = 419;

    private const string TRANSLATION_GROUP = 'errors';

    case BadRequest = 'BAD_REQUEST';
    case Unauthenticated = 'UNAUTHENTICATED';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case Forbidden = 'FORBIDDEN';
    case PasswordChangeRequired = 'PASSWORD_CHANGE_REQUIRED';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case InvalidStatusTransition = 'INVALID_STATUS_TRANSITION';
    case StatusUnchanged = 'STATUS_UNCHANGED';
    case AssigneeUnchanged = 'ASSIGNEE_UNCHANGED';
    case ConcurrentUpdate = 'CONCURRENT_UPDATE';
    case CurrentSessionNotClearable = 'CURRENT_SESSION_NOT_CLEARABLE';
    case CsrfTokenMismatch = 'CSRF_TOKEN_MISMATCH';
    case ValidationFailed = 'VALIDATION_FAILED';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case ServerError = 'SERVER_ERROR';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';

    public function httpStatus(): int
    {
        return match ($this) {
            self::BadRequest => Response::HTTP_BAD_REQUEST,
            self::Unauthenticated, self::InvalidCredentials => Response::HTTP_UNAUTHORIZED,
            self::Forbidden, self::PasswordChangeRequired => Response::HTTP_FORBIDDEN,
            self::NotFound => Response::HTTP_NOT_FOUND,
            self::MethodNotAllowed => Response::HTTP_METHOD_NOT_ALLOWED,
            self::Conflict, self::InvalidStatusTransition, self::StatusUnchanged, self::AssigneeUnchanged, self::ConcurrentUpdate, self::CurrentSessionNotClearable => Response::HTTP_CONFLICT,
            self::CsrfTokenMismatch => self::HTTP_CSRF_TOKEN_MISMATCH,
            self::ValidationFailed => Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TooManyRequests => Response::HTTP_TOO_MANY_REQUESTS,
            self::ServerError => Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ServiceUnavailable => Response::HTTP_SERVICE_UNAVAILABLE,
        };
    }

    public function message(): string
    {
        return __(self::TRANSLATION_GROUP.'.'.$this->value);
    }
}
