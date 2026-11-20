// Tells whether a failed API call is already handled by the HTTP client events and the route guards.
// Features skip their own error message for these failures, because the user is about to be redirected.

import { ErrorCode } from "@/enums/error-code";
import type { ApiError } from "@/lib/http/api-error";

const ROUTE_GUARD_ERROR_CODES: ReadonlySet<ErrorCode> = new Set([
  ErrorCode.Unauthenticated,
  ErrorCode.PasswordChangeRequired,
]);

export function isHandledByRouteGuards(error: ApiError | null): boolean {
  return error !== null && ROUTE_GUARD_ERROR_CODES.has(error.code);
}
