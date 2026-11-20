// Every page path in the application, defined in one place.
// Also maps each role to its home page and lists the pages open to signed out users.

import { UserRole } from "@/enums/user-role";

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

export const ROLE_HOME_ROUTES: Record<UserRole, AppRoute> = {
  [UserRole.Admin]: AppRoute.Overview,
  [UserRole.Technician]: AppRoute.Overview,
  [UserRole.Requester]: AppRoute.MyServiceRequests,
};

export const PUBLIC_ROUTES: ReadonlySet<string> = new Set([AppRoute.SignIn]);

export function serviceRequestDetailRoute(serviceRequestId: number): string {
  return `${AppRoute.ServiceRequests}/${serviceRequestId}`;
}

export function userDetailRoute(userId: number): string {
  return `${AppRoute.Users}/${userId}`;
}
