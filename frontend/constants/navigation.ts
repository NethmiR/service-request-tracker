// Text and items of the signed in navigation, listed in display order.
// Each later phase adds its item here in the same change as its page.

import { ADMIN_ROLES, ALL_ROLES, STAFF_ROLES } from "@/constants/role-groups";
import { AppRoute } from "@/constants/routes";
import type { NavigationItem } from "@/types/shared/navigation-item";

export const NavigationMessage = {
  NavigationLabel: "Main navigation",
  Overview: "Overview",
  MyServiceRequests: "My requests",
  NewServiceRequest: "New request",
  Users: "Users",
  Account: "Account",
  OpenMenu: "Open menu",
  CloseMenu: "Close menu",
  AccountSummaryLabel: "Signed in account",
} as const;

export const NAVIGATION_ITEMS: readonly NavigationItem[] = [
  {
    route: AppRoute.Overview,
    label: NavigationMessage.Overview,
    allowedRoles: STAFF_ROLES,
  },
  {
    route: AppRoute.MyServiceRequests,
    label: NavigationMessage.MyServiceRequests,
    allowedRoles: ALL_ROLES,
  },
  {
    route: AppRoute.NewServiceRequest,
    label: NavigationMessage.NewServiceRequest,
    allowedRoles: ALL_ROLES,
  },
  {
    route: AppRoute.Users,
    label: NavigationMessage.Users,
    allowedRoles: ADMIN_ROLES,
  },
  {
    route: AppRoute.Account,
    label: NavigationMessage.Account,
    allowedRoles: ALL_ROLES,
  },
];
