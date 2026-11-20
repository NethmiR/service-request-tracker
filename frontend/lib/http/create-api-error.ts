// Converts any failed request into the typed ApiError.
// Bodies that do not match the shared error shape become a generic server error.
// Rate limited responses also carry the Retry-After delay in whole seconds.

import { AxiosHeaders, isAxiosError, type AxiosError, type AxiosResponse } from "axios";
import { ERROR_MESSAGES } from "@/constants/error-messages";
import { HttpHeader, HttpStatus, NETWORK_FAILURE_STATUS } from "@/constants/http";
import { ErrorCode } from "@/enums/error-code";
import { ApiError } from "@/lib/http/api-error";
import type { ApiErrorResponse } from "@/types/shared/api-error-response";

export type ReceivedErrorBody =
  | Partial<Record<keyof ApiErrorResponse, string | object | null>>
  | string
  | null
  | undefined;

const errorCodeValues: ReadonlySet<string> = new Set(Object.values(ErrorCode));

const WHOLE_SECONDS_PATTERN = /^\d+$/;

function isErrorCode(value: string): value is ErrorCode {
  return errorCodeValues.has(value);
}

function readErrorCode(body: ReceivedErrorBody): ErrorCode | null {
  if (typeof body !== "object" || body === null) {
    return null;
  }

  const { code, message } = body;

  if (typeof code !== "string" || typeof message !== "string") {
    return null;
  }

  return isErrorCode(code) ? code : null;
}

function readFieldErrors(body: ReceivedErrorBody): Record<string, string[]> {
  if (typeof body !== "object" || body === null) {
    return {};
  }

  const { errors } = body;

  if (typeof errors !== "object" || errors === null || Array.isArray(errors)) {
    return {};
  }

  return Object.fromEntries(
    Object.entries(errors).filter(
      (entry): entry is [string, string[]] =>
        Array.isArray(entry[1]) &&
        entry[1].every((fieldMessage) => typeof fieldMessage === "string"),
    ),
  );
}

function readRetryAfterSeconds(response: AxiosResponse<ReceivedErrorBody>): number | null {
  if (response.status !== HttpStatus.TooManyRequests) {
    return null;
  }

  const { headers } = response;
  const headerValue =
    headers instanceof AxiosHeaders
      ? headers.get(HttpHeader.RetryAfter)
      : headers[HttpHeader.RetryAfter.toLowerCase()];
  const retryAfter = typeof headerValue === "number" ? String(headerValue) : headerValue;

  if (typeof retryAfter !== "string" || !WHOLE_SECONDS_PATTERN.test(retryAfter.trim())) {
    return null;
  }

  return Number.parseInt(retryAfter.trim(), 10);
}

function buildApiError(
  status: number,
  code: ErrorCode,
  fieldErrors: Record<string, string[]> = {},
  retryAfterSeconds: number | null = null,
): ApiError {
  return new ApiError({
    status,
    code,
    message: ERROR_MESSAGES[code],
    fieldErrors,
    retryAfterSeconds,
  });
}

function fromAxiosError(error: AxiosError<ReceivedErrorBody>): ApiError {
  const { response } = error;

  if (!response) {
    return buildApiError(NETWORK_FAILURE_STATUS, ErrorCode.ServiceUnavailable);
  }

  const code = readErrorCode(response.data);

  if (code === null) {
    return buildApiError(response.status, ErrorCode.ServerError);
  }

  return buildApiError(
    response.status,
    code,
    readFieldErrors(response.data),
    readRetryAfterSeconds(response),
  );
}

export function createApiError(error: Error): ApiError {
  if (error instanceof ApiError) {
    return error;
  }

  if (isAxiosError<ReceivedErrorBody>(error)) {
    return fromAxiosError(error);
  }

  return buildApiError(NETWORK_FAILURE_STATUS, ErrorCode.ServerError);
}
