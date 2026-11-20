// One entry of the signed in navigation, with its route, its label and the roles that see it.
// The sidebar filters the configured items by the signed in user's role.

import type { AppRoute } from "@/constants/routes";
import type { UserRole } from "@/enums/user-role";

export interface NavigationItem {
  route: AppRoute;
  label: string;
  allowedRoles: readonly UserRole[];
}
