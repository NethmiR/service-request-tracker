// Turns a failed auth form submission into the alert message and field errors the form shows.
// Shared by the sign in and change password forms so this error handling is written once.

import { formatRetryAfterMessage } from "@/constants/auth-messages";
import { ERROR_MESSAGES } from "@/constants/error-messages";
import { ErrorCode } from "@/enums/error-code";
import type { ApiError } from "@/lib/http/api-error";

export interface AuthFormErrors<Field extends string> {
  message: string | null;
  fieldErrors: Partial<Record<Field, string>>;
}

export function emptyAuthFormErrors<Field extends string>(): AuthFormErrors<Field> {
  return { message: null, fieldErrors: {} };
}

export function resolveAuthFormErrors<Field extends string>(
  error: ApiError | null,
  fields: readonly Field[],
): AuthFormErrors<Field> {
  if (error === null) {
    return { message: ERROR_MESSAGES[ErrorCode.ServerError], fieldErrors: {} };
  }

  if (error.hasCode(ErrorCode.ValidationFailed)) {
    const fieldErrors: Partial<Record<Field, string>> = {};

    for (const field of fields) {
      const fieldError = error.firstFieldError(field);

      if (fieldError !== undefined) {
        fieldErrors[field] = fieldError;
      }
    }

    const hasFieldErrors = Object.keys(fieldErrors).length > 0;

    return { message: hasFieldErrors ? null : error.message, fieldErrors };
  }

  if (error.hasCode(ErrorCode.TooManyRequests) && error.retryAfterSeconds !== null) {
    return { message: formatRetryAfterMessage(error.retryAfterSeconds), fieldErrors: {} };
  }

  return { message: error.message, fieldErrors: {} };
}
