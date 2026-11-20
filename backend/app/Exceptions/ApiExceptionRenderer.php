<?php

// Maps every exception to the shared JSON error shape.
// This is the only place where exceptions become HTTP responses.

namespace App\Exceptions;

use App\Enums\ErrorCode;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public function render(Throwable $exception): ?ApiErrorResponse
    {
        return match (true) {
            $exception instanceof HttpResponseException => null,
            $exception instanceof DomainException => new ApiErrorResponse($exception->errorCode()),
            $exception instanceof AuthenticationException => new ApiErrorResponse(ErrorCode::Unauthenticated),
            $exception instanceof ValidationException => new ApiErrorResponse(ErrorCode::ValidationFailed, $exception->errors()),
            $exception instanceof HttpExceptionInterface => new ApiErrorResponse(
                $this->errorCodeForStatus($exception->getStatusCode()),
                headers: $exception->getHeaders(),
            ),
            default => new ApiErrorResponse(ErrorCode::ServerError),
        };
    }

    private function errorCodeForStatus(int $status): ErrorCode
    {
        return match (true) {
            $status === Response::HTTP_UNAUTHORIZED => ErrorCode::Unauthenticated,
            $status === Response::HTTP_FORBIDDEN => ErrorCode::Forbidden,
            $status === Response::HTTP_NOT_FOUND => ErrorCode::NotFound,
            $status === Response::HTTP_METHOD_NOT_ALLOWED => ErrorCode::MethodNotAllowed,
            $status === Response::HTTP_CONFLICT => ErrorCode::Conflict,
            $status === ErrorCode::HTTP_CSRF_TOKEN_MISMATCH => ErrorCode::CsrfTokenMismatch,
            $status === Response::HTTP_TOO_MANY_REQUESTS => ErrorCode::TooManyRequests,
            $status === Response::HTTP_SERVICE_UNAVAILABLE => ErrorCode::ServiceUnavailable,
            $status >= Response::HTTP_BAD_REQUEST && $status < Response::HTTP_INTERNAL_SERVER_ERROR => ErrorCode::BadRequest,
            default => ErrorCode::ServerError,
        };
    }
}
