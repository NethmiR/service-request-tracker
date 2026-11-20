// Decides where a signed in user belongs, used after sign in and by the route guards.
// A pending password change always wins over the role home page.

import { AppRoute, ROLE_HOME_ROUTES } from "@/constants/routes";
import type { CurrentUser } from "@/types/auth/current-user";

export function resolveSignedInRoute(user: CurrentUser): AppRoute {
  return user.password_change_required ? AppRoute.ChangePassword : ROLE_HOME_ROUTES[user.role];
}
