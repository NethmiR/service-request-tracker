// Fetches the Sanctum CSRF cookie that protects every state changing request.
// Concurrent callers share one in flight request so the cookie is never fetched twice at once.

import axios from "axios";
import { environment } from "@/config/environment";
import { ApiEndpoint } from "@/constants/api-endpoints";
import {
  HttpHeader,
  JSON_MEDIA_TYPE,
  REQUEST_TIMEOUT_MILLISECONDS,
  XML_HTTP_REQUEST,
  XSRF_COOKIE_NAME,
} from "@/constants/http";

let pendingCsrfRequest: Promise<void> | null = null;

function hasCsrfCookie(): boolean {
  if (typeof document === "undefined") {
    return false;
  }

  return document.cookie
    .split(";")
    .some((cookie) => cookie.trim().startsWith(`${XSRF_COOKIE_NAME}=`));
}

export function refreshCsrfCookie(): Promise<void> {
  if (pendingCsrfRequest === null) {
    pendingCsrfRequest = axios
      .get(ApiEndpoint.CsrfCookie, {
        baseURL: environment.apiBaseUrl,
        withCredentials: true,
        timeout: REQUEST_TIMEOUT_MILLISECONDS,
        headers: {
          [HttpHeader.Accept]: JSON_MEDIA_TYPE,
          [HttpHeader.RequestedWith]: XML_HTTP_REQUEST,
        },
      })
      .then(() => undefined)
      .finally(() => {
        pendingCsrfRequest = null;
      });
  }

  return pendingCsrfRequest;
}

export function ensureCsrfCookie(): Promise<void> {
  return hasCsrfCookie() ? Promise.resolve() : refreshCsrfCookie();
}
