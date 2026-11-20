// Every backend endpoint the frontend calls, relative to the API base URL.
// Each phase adds its endpoints here together with the matching backend routes.

export enum ApiEndpoint {
  CsrfCookie = "/sanctum/csrf-cookie",
  SignIn = "/api/auth/sign-in",
  SignOut = "/api/auth/sign-out",
  CurrentUser = "/api/auth/user",
  ChangePassword = "/api/auth/password",
}
