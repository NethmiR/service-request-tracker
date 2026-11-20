// Creates the shared Axios instance used by every feature service.
// Configures credentials, the XSRF header and the interceptors that turn failures into ApiError.

import axios, { isAxiosError, isCancel, type AxiosResponse } from "axios";
import { environment } from "@/config/environment";
import {
  HttpHeader,
  HttpStatus,
  JSON_MEDIA_TYPE,
  REQUEST_TIMEOUT_MILLISECONDS,
  STATE_CHANGING_HTTP_METHODS,
  XML_HTTP_REQUEST,
  XSRF_COOKIE_NAME,
  XSRF_HEADER_NAME,
} from "@/constants/http";
import { ErrorCode } from "@/enums/error-code";
import { createApiError, type ReceivedErrorBody } from "@/lib/http/create-api-error";
import { ensureCsrfCookie, refreshCsrfCookie } from "@/lib/http/csrf-cookie";
import { HttpEvent, httpEvents } from "@/lib/http/http-events";

export const httpClient = axios.create({
  baseURL: environment.apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: XSRF_COOKIE_NAME,
  xsrfHeaderName: XSRF_HEADER_NAME,
  timeout: REQUEST_TIMEOUT_MILLISECONDS,
  headers: {
    [HttpHeader.Accept]: JSON_MEDIA_TYPE,
    [HttpHeader.RequestedWith]: XML_HTTP_REQUEST,
  },
});

httpClient.interceptors.request.use(async (config) => {
  if (config.method && STATE_CHANGING_HTTP_METHODS.has(config.method.toLowerCase())) {
    await ensureCsrfCookie().catch((error: Error) => {
      throw createApiError(error);
    });
  }

  return config;
});

async function handleResponseError(error: Error): Promise<AxiosResponse> {
  if (isCancel(error)) {
    throw error;
  }

  if (isAxiosError<ReceivedErrorBody>(error)) {
    const { config, response } = error;

    if (
      response?.status === HttpStatus.CsrfTokenMismatch &&
      config &&
      !config.hasRetriedCsrfToken
    ) {
      config.hasRetriedCsrfToken = true;

      await refreshCsrfCookie().catch((refreshError: Error) => {
        throw createApiError(refreshError);
      });

      return httpClient(config);
    }
  }

  const apiError = createApiError(error);

  if (apiError.status === HttpStatus.Unauthorized) {
    httpEvents.publish(HttpEvent.Unauthenticated);
  }

  if (
    apiError.status === HttpStatus.Forbidden &&
    apiError.hasCode(ErrorCode.PasswordChangeRequired)
  ) {
    httpEvents.publish(HttpEvent.PasswordChangeRequired);
  }

  throw apiError;
}

httpClient.interceptors.response.use((response) => response, handleResponseError);
