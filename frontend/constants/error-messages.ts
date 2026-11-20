// User facing message for every error code the API can return.
// The typed record makes the build fail when a code has no message.

import { ErrorCode } from "@/enums/error-code";

export const ERROR_MESSAGES: Record<ErrorCode, string> = {
  [ErrorCode.BadRequest]: "The request could not be processed.",
  [ErrorCode.Unauthenticated]: "Please sign in to continue.",
  [ErrorCode.InvalidCredentials]: "The email or password is incorrect.",
  [ErrorCode.Forbidden]: "You do not have permission to perform this action.",
  [ErrorCode.PasswordChangeRequired]: "You must change your password before continuing.",
  [ErrorCode.NotFound]: "The requested resource was not found.",
  [ErrorCode.MethodNotAllowed]: "This action is not supported.",
  [ErrorCode.Conflict]: "This change conflicts with the current state of the request.",
  [ErrorCode.InvalidStatusTransition]: "The request cannot be moved to the selected status.",
  [ErrorCode.StatusUnchanged]: "The request already has the selected status.",
  [ErrorCode.ConcurrentUpdate]:
    "Someone else changed this request first. Please review the latest version and try again.",
  [ErrorCode.CurrentSessionNotClearable]:
    "The current session cannot be ended here. Use sign out instead.",
  [ErrorCode.CsrfTokenMismatch]: "Your session has expired. Please refresh the page and try again.",
  [ErrorCode.ValidationFailed]: "Please correct the highlighted fields.",
  [ErrorCode.TooManyRequests]: "Too many attempts. Please wait and try again.",
  [ErrorCode.ServerError]: "Something went wrong. Please try again later.",
  [ErrorCode.ServiceUnavailable]:
    "The service cannot be reached right now. Please check your connection and try again.",
};
