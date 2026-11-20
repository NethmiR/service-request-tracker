// Shape of every error body returned by the API.
// Mirrors the backend ApiErrorResponse in snake_case.

import type { ErrorCode } from "@/enums/error-code";

export interface ApiErrorResponse {
  message: string;
  code: ErrorCode;
  errors?: Record<string, string[]>;
}
