// Turns a failed form submission into the alert message and field errors the form shows.
// Shared by every form so this error handling is written once.

import { ERROR_MESSAGES } from "@/constants/error-messages";
import { formatRetryAfterMessage } from "@/constants/ui-messages";
import { ErrorCode } from "@/enums/error-code";
import type { ApiError } from "@/lib/http/api-error";

export interface FormErrors<Field extends string> {
  message: string | null;
  fieldErrors: Partial<Record<Field, string>>;
}

export function emptyFormErrors<Field extends string>(): FormErrors<Field> {
  return { message: null, fieldErrors: {} };
}

export function resolveFormErrors<Field extends string>(
  error: ApiError | null,
  fields: readonly Field[],
): FormErrors<Field> {
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
