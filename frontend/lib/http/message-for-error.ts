// Returns the message to show for a failed API call.
// Callers pass null for any error that is not an ApiError, which shows the generic server error.

import { ERROR_MESSAGES } from "@/constants/error-messages";
import { ErrorCode } from "@/enums/error-code";
import type { ApiError } from "@/lib/http/api-error";

export function messageForError(error: ApiError | null): string {
  return error?.message ?? ERROR_MESSAGES[ErrorCode.ServerError];
}
