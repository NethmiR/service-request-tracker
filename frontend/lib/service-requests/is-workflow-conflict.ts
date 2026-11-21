// Tells whether a failed workflow call means the request changed under the user.
// Any conflict response and a forbidden response both lead to a reload of the latest request.

import { HttpStatus } from "@/constants/http";
import { ErrorCode } from "@/enums/error-code";
import type { ApiError } from "@/lib/http/api-error";

export function isWorkflowConflict(error: ApiError | null): boolean {
  return (
    error !== null && (error.status === HttpStatus.Conflict || error.hasCode(ErrorCode.Forbidden))
  );
}
