// Every backend endpoint the frontend calls, relative to the API base URL.
// Each phase adds its endpoints here together with the matching backend routes.

export enum ApiEndpoint {
  CsrfCookie = "/sanctum/csrf-cookie",
}
