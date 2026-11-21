// Text and items of the signed in navigation, listed in display order.
// Each later phase adds its item here in the same change as its page.

import { ADMIN_ROLES, ALL_ROLES, STAFF_ROLES } from "@/constants/role-groups";
import { AppRoute } from "@/constants/routes";
import { NavigationItemKind } from "@/enums/ui/navigation-item-kind";
import type { NavigationItem } from "@/types/shared/navigation-item";

export const NavigationMessage = {
  NavigationLabel: "Main navigation",
  Overview: "Overview",
  ServiceRequests: "All requests",
  MyServiceRequestsGroup: "My requests",
  MyServiceRequests: "My requests history",
  NewServiceRequest: "New request",
  Users: "Users",
  Account: "Account",
  OpenMenu: "Open menu",
  CloseMenu: "Close menu",
  AccountSummaryLabel: "Signed in account",
} as const;

export const NAVIGATION_ITEMS: readonly NavigationItem[] = [
  {
    kind: NavigationItemKind.Link,
    route: AppRoute.Overview,
    label: NavigationMessage.Overview,
    allowedRoles: STAFF_ROLES,
  },
  {
    kind: NavigationItemKind.Link,
    route: AppRoute.ServiceRequests,
    label: NavigationMessage.ServiceRequests,
    allowedRoles: STAFF_ROLES,
  },
  {
    kind: NavigationItemKind.Group,
    label: NavigationMessage.MyServiceRequestsGroup,
    links: [
      {
        kind: NavigationItemKind.Link,
        route: AppRoute.MyServiceRequests,
        label: NavigationMessage.MyServiceRequests,
        allowedRoles: ALL_ROLES,
      },
      {
        kind: NavigationItemKind.Link,
        route: AppRoute.NewServiceRequest,
        label: NavigationMessage.NewServiceRequest,
        allowedRoles: ALL_ROLES,
      },
    ],
  },
  {
    kind: NavigationItemKind.Link,
    route: AppRoute.Users,
    label: NavigationMessage.Users,
    allowedRoles: ADMIN_ROLES,
  },
  {
    kind: NavigationItemKind.Link,
    route: AppRoute.Account,
    label: NavigationMessage.Account,
    allowedRoles: ALL_ROLES,
  },
];
