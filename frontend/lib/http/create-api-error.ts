// Converts any failed request into the typed ApiError.
// Bodies that do not match the shared error shape become a generic server error.

import { isAxiosError, type AxiosError } from "axios";
import { ERROR_MESSAGES } from "@/constants/error-messages";
import { NETWORK_FAILURE_STATUS } from "@/constants/http";
import { ErrorCode } from "@/enums/error-code";
import { ApiError } from "@/lib/http/api-error";
import type { ApiErrorResponse } from "@/types/shared/api-error-response";

export type ReceivedErrorBody =
  | Partial<Record<keyof ApiErrorResponse, string | object | null>>
  | string
  | null
  | undefined;

const errorCodeValues: ReadonlySet<string> = new Set(Object.values(ErrorCode));

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

function buildApiError(
  status: number,
  code: ErrorCode,
  fieldErrors: Record<string, string[]> = {},
): ApiError {
  return new ApiError({ status, code, message: ERROR_MESSAGES[code], fieldErrors });
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

  return buildApiError(response.status, code, readFieldErrors(response.data));
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
