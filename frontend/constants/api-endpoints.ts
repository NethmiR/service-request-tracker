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
  Users = "/api/users",
  ServiceRequests = "/api/service-requests",
  MyServiceRequests = "/api/service-requests/mine",
  MyServiceRequestAssignees = "/api/service-requests/mine/assignees",
  ServiceRequestAssignees = "/api/service-requests/assignees",
  Overview = "/api/overview",
}

export function sessionEndpoint(sessionIdentifier: string): string {
  return `${ApiEndpoint.Sessions}/${encodeURIComponent(sessionIdentifier)}`;
}

export function userEndpoint(userId: number): string {
  return `${ApiEndpoint.Users}/${userId}`;
}

export function userPasswordResetEndpoint(userId: number): string {
  return `${userEndpoint(userId)}/password-reset`;
}

export function userSessionsEndpoint(userId: number): string {
  return `${userEndpoint(userId)}/sessions`;
}

export function userSessionEndpoint(userId: number, sessionIdentifier: string): string {
  return `${userSessionsEndpoint(userId)}/${encodeURIComponent(sessionIdentifier)}`;
}

export function serviceRequestEndpoint(serviceRequestId: number): string {
  return `${ApiEndpoint.ServiceRequests}/${serviceRequestId}`;
}

export function serviceRequestAssignmentEndpoint(serviceRequestId: number): string {
  return `${serviceRequestEndpoint(serviceRequestId)}/assignment`;
}

export function serviceRequestReassignmentEndpoint(serviceRequestId: number): string {
  return `${serviceRequestEndpoint(serviceRequestId)}/reassignment`;
}

export function serviceRequestStatusEndpoint(serviceRequestId: number): string {
  return `${serviceRequestEndpoint(serviceRequestId)}/status`;
}
