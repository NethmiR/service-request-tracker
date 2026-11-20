<?php

// User facing messages for every error code returned by the API.
// Messages stay short and never reveal internal details.

return [
    'BAD_REQUEST' => 'The request could not be processed.',
    'UNAUTHENTICATED' => 'Please sign in to continue.',
    'INVALID_CREDENTIALS' => 'The email or password is incorrect.',
    'FORBIDDEN' => 'You do not have permission to perform this action.',
    'PASSWORD_CHANGE_REQUIRED' => 'You must change your password before continuing.',
    'NOT_FOUND' => 'The requested resource was not found.',
    'METHOD_NOT_ALLOWED' => 'This action is not supported for the requested resource.',
    'CONFLICT' => 'The request conflicts with the current state of the resource.',
    'INVALID_STATUS_TRANSITION' => 'The request cannot be moved to the selected status.',
    'STATUS_UNCHANGED' => 'The request already has the selected status.',
    'CONCURRENT_UPDATE' => 'The request was changed by someone else. Please reload and try again.',
    'CSRF_TOKEN_MISMATCH' => 'Your session has expired. Please refresh the page and try again.',
    'VALIDATION_FAILED' => 'The given data was invalid.',
    'TOO_MANY_REQUESTS' => 'Too many attempts. Please wait and try again.',
    'SERVER_ERROR' => 'Something went wrong. Please try again later.',
    'SERVICE_UNAVAILABLE' => 'The service is temporarily unavailable. Please try again later.',
];
