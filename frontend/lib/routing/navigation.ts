// Pure helpers that filter the navigation items by role and find the item matching the current path.
// The longest matching route wins so only one item is active when routes share a prefix.

import { NAVIGATION_ITEMS } from "@/constants/navigation";
import { NavigationItemKind } from "@/enums/ui/navigation-item-kind";
import type { UserRole } from "@/enums/user-role";
import type { NavigationItem, NavigationLink } from "@/types/shared/navigation-item";

const PATH_SEPARATOR = "/";

function isLinkAllowedForRole(link: NavigationLink, role: UserRole): boolean {
  return link.allowedRoles.includes(role);
}

export function navigationItemsForRole(role: UserRole): NavigationItem[] {
  return NAVIGATION_ITEMS.flatMap<NavigationItem>((item) => {
    if (item.kind === NavigationItemKind.Link) {
      return isLinkAllowedForRole(item, role) ? [item] : [];
    }

    const links = item.links.filter((link) => isLinkAllowedForRole(link, role));

    return links.length > 0 ? [{ ...item, links }] : [];
  });
}

export function flattenNavigationLinks(items: readonly NavigationItem[]): NavigationLink[] {
  return items.flatMap((item) => (item.kind === NavigationItemKind.Link ? [item] : item.links));
}

function isParentPath(route: string, pathname: string): boolean {
  return pathname.startsWith(`${route}${PATH_SEPARATOR}`);
}

export function findActiveNavigationLink(
  links: readonly NavigationLink[],
  pathname: string,
): NavigationLink | null {
  const exactMatch = links.find((link) => link.route === pathname);

  if (exactMatch !== undefined) {
    return exactMatch;
  }

  return links.reduce<NavigationLink | null>((longestMatch, link) => {
    if (!isParentPath(link.route, pathname)) {
      return longestMatch;
    }

    if (longestMatch === null || link.route.length > longestMatch.route.length) {
      return link;
    }

    return longestMatch;
  }, null);
}
