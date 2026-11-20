// HTTP statuses, headers, methods and limits the HTTP client relies on.
// Keeps protocol level values out of the request logic.

export enum HttpStatus {
  Unauthorized = 401,
  Forbidden = 403,
  CsrfTokenMismatch = 419,
  UnprocessableContent = 422,
  TooManyRequests = 429,
}

export enum HttpHeader {
  Accept = "Accept",
  RequestedWith = "X-Requested-With",
  RetryAfter = "Retry-After",
}

export enum HttpMethod {
  Get = "get",
  Post = "post",
  Patch = "patch",
  Delete = "delete",
}

export const STATE_CHANGING_HTTP_METHODS: ReadonlySet<string> = new Set([
  HttpMethod.Post,
  HttpMethod.Patch,
  HttpMethod.Delete,
]);

export const JSON_MEDIA_TYPE = "application/json";

export const XML_HTTP_REQUEST = "XMLHttpRequest";

export const XSRF_COOKIE_NAME = "XSRF-TOKEN";

export const XSRF_HEADER_NAME = "X-XSRF-TOKEN";

export const NETWORK_FAILURE_STATUS = 0;

export const REQUEST_TIMEOUT_MILLISECONDS = 15000;
