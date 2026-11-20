// Error codes returned by the API, mirrored exactly from the backend ErrorCode enum.
// New codes are added here in the same change as the backend.

export enum ErrorCode {
  BadRequest = "BAD_REQUEST",
  Unauthenticated = "UNAUTHENTICATED",
  InvalidCredentials = "INVALID_CREDENTIALS",
  Forbidden = "FORBIDDEN",
  PasswordChangeRequired = "PASSWORD_CHANGE_REQUIRED",
  NotFound = "NOT_FOUND",
  MethodNotAllowed = "METHOD_NOT_ALLOWED",
  Conflict = "CONFLICT",
  InvalidStatusTransition = "INVALID_STATUS_TRANSITION",
  StatusUnchanged = "STATUS_UNCHANGED",
  AssigneeUnchanged = "ASSIGNEE_UNCHANGED",
  ConcurrentUpdate = "CONCURRENT_UPDATE",
  CurrentSessionNotClearable = "CURRENT_SESSION_NOT_CLEARABLE",
  CsrfTokenMismatch = "CSRF_TOKEN_MISMATCH",
  ValidationFailed = "VALIDATION_FAILED",
  TooManyRequests = "TOO_MANY_REQUESTS",
  ServerError = "SERVER_ERROR",
  ServiceUnavailable = "SERVICE_UNAVAILABLE",
}
