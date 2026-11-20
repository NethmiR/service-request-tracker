// Every page path in the application, defined in one place.
// Dynamic paths are built through the helper functions below.

export enum AppRoute {
  Root = "/",
  SignIn = "/sign-in",
  ChangePassword = "/change-password",
  Overview = "/overview",
  ServiceRequests = "/service-requests",
  MyServiceRequests = "/service-requests/mine",
  NewServiceRequest = "/service-requests/new",
  Users = "/users",
  Account = "/account",
}

export function serviceRequestDetailRoute(serviceRequestId: number): string {
  return `${AppRoute.ServiceRequests}/${serviceRequestId}`;
}
