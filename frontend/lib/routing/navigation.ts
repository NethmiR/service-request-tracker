// Pure helpers that filter the navigation items by role and find the item matching the current path.
// The longest matching route wins so only one item is active when routes share a prefix.

import { NAVIGATION_ITEMS } from "@/constants/navigation";
import type { UserRole } from "@/enums/user-role";
import type { NavigationItem } from "@/types/shared/navigation-item";

const PATH_SEPARATOR = "/";

export function navigationItemsForRole(role: UserRole): NavigationItem[] {
  return NAVIGATION_ITEMS.filter((item) => item.allowedRoles.includes(role));
}

function isParentPath(route: string, pathname: string): boolean {
  return pathname.startsWith(`${route}${PATH_SEPARATOR}`);
}

export function findActiveNavigationItem(
  items: readonly NavigationItem[],
  pathname: string,
): NavigationItem | null {
  const exactMatch = items.find((item) => item.route === pathname);

  if (exactMatch !== undefined) {
    return exactMatch;
  }

  return items.reduce<NavigationItem | null>((longestMatch, item) => {
    if (!isParentPath(item.route, pathname)) {
      return longestMatch;
    }

    if (longestMatch === null || item.route.length > longestMatch.route.length) {
      return item;
    }

    return longestMatch;
  }, null);
}
