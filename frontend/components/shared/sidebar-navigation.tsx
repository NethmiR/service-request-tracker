// Navigation landmark of the signed in layout, showing only the items allowed for the user's role.
// Marks the item matching the current path as the current page.

"use client";

import { usePathname } from "next/navigation";
import { SidebarNavigationGroup } from "@/components/shared/sidebar-navigation-group";
import { SidebarNavigationLink } from "@/components/shared/sidebar-navigation-link";
import { NavigationMessage } from "@/constants/navigation";
import { NavigationItemKind } from "@/enums/ui/navigation-item-kind";
import { useAuth } from "@/hooks/auth/use-auth";
import {
  findActiveNavigationLink,
  flattenNavigationLinks,
  navigationItemsForRole,
} from "@/lib/routing/navigation";
import type { NavigationItem } from "@/types/shared/navigation-item";

interface SidebarNavigationProps {
  onNavigate?: () => void;
  className?: string;
}

function navigationItemKey(item: NavigationItem): string {
  return item.kind === NavigationItemKind.Link ? item.route : item.label;
}

export function SidebarNavigation({ onNavigate, className }: SidebarNavigationProps) {
  const { user } = useAuth();
  const pathname = usePathname();

  if (user === null) {
    return null;
  }

  const items = navigationItemsForRole(user.role);
  const activeLink = findActiveNavigationLink(flattenNavigationLinks(items), pathname);

  return (
    <nav aria-label={NavigationMessage.NavigationLabel} className={className}>
      <ul className="flex flex-col gap-1">
        {items.map((item) => (
          <li key={navigationItemKey(item)}>
            {item.kind === NavigationItemKind.Link ? (
              <SidebarNavigationLink
                link={item}
                isActive={item.route === activeLink?.route}
                onNavigate={onNavigate}
              />
            ) : (
              <SidebarNavigationGroup group={item} activeLink={activeLink} onNavigate={onNavigate} />
            )}
          </li>
        ))}
      </ul>
    </nav>
  );
}
