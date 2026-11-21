// One entry of the signed in navigation, with its route, its label and the roles that see it.
// The sidebar filters the configured items by the signed in user's role.

import type { AppRoute } from "@/constants/routes";
import type { NavigationItemKind } from "@/enums/ui/navigation-item-kind";
import type { UserRole } from "@/enums/user-role";

export interface NavigationLink {
  kind: NavigationItemKind.Link;
  route: AppRoute;
  label: string;
  allowedRoles: readonly UserRole[];
}

export interface NavigationGroup {
  kind: NavigationItemKind.Group;
  label: string;
  links: readonly NavigationLink[];
}

export type NavigationItem = NavigationLink | NavigationGroup;
