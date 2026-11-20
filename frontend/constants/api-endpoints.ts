// Every backend endpoint the frontend calls, relative to the API base URL.
// Each phase adds its endpoints here together with the matching backend routes.
// Endpoints with a path parameter are built by the helper functions below the enum.

export enum ApiEndpoint {
  CsrfCookie = "/sanctum/csrf-cookie",
  SignIn = "/api/auth/sign-in",
  SignOut = "/api/auth/sign-out",
  CurrentUser = "/api/auth/user",
  ChangePassword = "/api/auth/password",
  Sessions = "/api/sessions",
  OtherSessions = "/api/sessions/others",
}

export function sessionEndpoint(sessionIdentifier: string): string {
  return `${ApiEndpoint.Sessions}/${encodeURIComponent(sessionIdentifier)}`;
}
