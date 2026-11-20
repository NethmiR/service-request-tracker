// The only error type the application sees for failed API calls.
// Carries the HTTP status, the shared error code, the UI message and field errors.

import type { ErrorCode } from "@/enums/error-code";

export interface ApiErrorDetails {
  status: number;
  code: ErrorCode;
  message: string;
  fieldErrors?: Record<string, string[]>;
}

export class ApiError extends Error {
  readonly status: number;
  readonly code: ErrorCode;
  readonly fieldErrors: Record<string, string[]>;

  constructor({ status, code, message, fieldErrors = {} }: ApiErrorDetails) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = code;
    this.fieldErrors = fieldErrors;
  }

  firstFieldError(field: string): string | undefined {
    return this.fieldErrors[field]?.[0];
  }

  hasCode(code: ErrorCode): boolean {
    return this.code === code;
  }
}
