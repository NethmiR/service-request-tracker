<?php

// Builds the single JSON error shape returned for every failed request.
// This is the only place where the error body is assembled.

namespace App\Http\Responses;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

final class ApiErrorResponse extends JsonResponse
{
    public function __construct(ErrorCode $errorCode, array $fieldErrors = [], array $headers = [])
    {
        $body = [
            'message' => $errorCode->message(),
            'code' => $errorCode->value,
        ];

        if ($fieldErrors !== []) {
            $body['errors'] = $fieldErrors;
        }

        parent::__construct($body, $errorCode->httpStatus(), $headers);
    }
}
